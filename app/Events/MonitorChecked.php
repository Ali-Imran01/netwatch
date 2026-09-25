<?php

namespace App\Events;

use App\Models\Monitor;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Sent after every check so the status board updates state, latency and timestamps live; `state_changed` marks a flip. */
class MonitorChecked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    // Default queue, not `checks`: a slow broadcast must never delay probes.
    public string $queue = 'default';

    public function __construct(public Monitor $monitor, public bool $stateChanged) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('monitors')];
    }

    public function broadcastAs(): string
    {
        return 'MonitorChecked';
    }

    public function broadcastWith(): array
    {
        $m = $this->monitor;

        return [
            'id' => $m->id,
            'state' => $m->state->value,
            'state_changed' => $this->stateChanged,
            'state_changed_at' => $m->state_changed_at?->toIso8601String(),
            'last_success' => $m->last_success,
            'last_latency_ms' => $m->last_latency_ms,
            'last_checked_at' => $m->last_checked_at?->toIso8601String(),
            'in_maintenance' => $m->inMaintenance(),
        ];
    }
}
