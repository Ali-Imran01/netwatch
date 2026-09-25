<?php

namespace App\Jobs;

use App\Alerts\AlertMessage;
use App\Alerts\TelegramClient;
use App\Mail\IncidentAlert;
use App\Models\AlertChannel;
use App\Models\Incident;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendAlert implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public int $channelId, public int $incidentId, public string $kind) {}

    public function handle(TelegramClient $telegram): void
    {
        $channel = AlertChannel::where('enabled', true)->find($this->channelId);
        $incident = Incident::find($this->incidentId);
        if (! $channel || ! $incident) {
            return; // deleted or switched off since the alert was queued
        }

        if ($channel->type === 'email') {
            Mail::to($channel->target)->send(new IncidentAlert($incident, $this->kind));

            return;
        }

        // Only a fresh incident gets the button; acking a resolved one makes no sense.
        $keyboard = $this->kind === 'opened' ? [[['text' => 'Acknowledge', 'callback_data' => "ack:{$incident->id}"]]] : null;
        $telegram->sendMessage($channel->target, AlertMessage::body($incident, $this->kind), $keyboard);
    }
}
