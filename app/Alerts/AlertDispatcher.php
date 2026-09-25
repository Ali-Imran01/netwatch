<?php

namespace App\Alerts;

use App\Jobs\SendAlert;
use App\Models\AlertChannel;
use App\Models\Incident;

/** Fans an incident notification out to every enabled channel, one queued job each so one broken channel never blocks the rest. */
class AlertDispatcher
{
    public function opened(Incident $incident): void
    {
        $this->send($incident, 'opened');
    }

    public function resolved(Incident $incident): void
    {
        $this->send($incident, 'resolved');
    }

    private function send(Incident $incident, string $kind): void
    {
        AlertChannel::where('enabled', true)->pluck('id')->each(fn (int $id) => SendAlert::dispatch($id, $incident->id, $kind));
    }
}
