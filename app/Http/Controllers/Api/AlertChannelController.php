<?php

namespace App\Http\Controllers\Api;

use App\Alerts\TelegramClient;
use App\Mail\IncidentAlert;
use App\Models\AlertChannel;
use App\Models\Incident;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

class AlertChannelController extends InventoryController
{
    protected function model(): string
    {
        return AlertChannel::class;
    }

    protected function rules(?Model $record, array $input = []): array
    {
        $type = $input['type'] ?? null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['telegram', 'email'])],
            // Telegram chat ids are integers (negative for groups) or an @channel name.
            'target' => ['required', 'string', 'max:255', $type === 'email' ? 'email' : 'regex:/^(-?\d+|@[A-Za-z0-9_]{4,})$/'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    /** Send a test message so a new channel can be checked before an outage depends on it. */
    public function test(int $id, TelegramClient $telegram): JsonResponse
    {
        $channel = AlertChannel::findOrFail($id);
        Gate::authorize('update', $channel);

        try {
            if ($channel->type === 'email') {
                Mail::to($channel->target)->send(new IncidentAlert($this->sample(), 'opened'));
            } else {
                $telegram->sendMessage($channel->target, 'NetWatch test alert: this channel is connected.');
            }
        } catch (Throwable $e) {
            return response()->json(['message' => "Test failed: {$e->getMessage()}"], 502);
        }

        return response()->json(['message' => "Test sent to {$channel->target}."]);
    }

    /** An unsaved incident, so a test email looks like a real one without creating a record. */
    private function sample(): Incident
    {
        $incident = Incident::make(['severity' => 'major']);
        $incident->forceFill(['id' => 0, 'title' => 'Test alert (not a real incident)', 'severity' => 'major', 'opened_at' => now()]);
        $incident->setRelation('monitor', new \App\Models\Monitor(['name' => 'test-monitor', 'type' => 'ping', 'target' => '192.0.2.1']));
        $incident->setRelation('circuit', null);

        return $incident;
    }
}
