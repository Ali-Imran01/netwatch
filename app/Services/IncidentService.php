<?php

namespace App\Services;

use App\Alerts\AlertDispatcher;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentState;
use App\Enums\MonitorState;
use App\Models\Circuit;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IncidentService
{
    public function __construct(private AlertDispatcher $alerts) {}

    /**
     * Called after every check. A Down monitor with no open incident gets one, unless it is under maintenance;
     * checking on every failure (not only on the flip) also covers a link that was already Down when a window ended.
     * A monitor that just came back Up resolves the incidents that recovery may resolve on its own.
     */
    public function sync(Monitor $monitor, bool $stateChanged): void
    {
        if ($monitor->state === MonitorState::Down) {
            if (! $monitor->inMaintenance()) {
                $this->openFor($monitor);
            }

            return;
        }

        if ($monitor->state === MonitorState::Up && $stateChanged) {
            Incident::where('monitor_id', $monitor->id)->whereIn('state', IncidentState::openValues())->get()
                ->filter(fn (Incident $i) => $i->state->autoResolves())
                ->each(fn (Incident $i) => $this->transition($i, IncidentState::Resolved, null, 'Service recovered (automatic).'));
        }
    }

    /** Open an incident unless the monitor already has one that is still open. */
    public function openFor(Monitor $monitor): ?Incident
    {
        $incident = DB::transaction(function () use ($monitor) {
            // Lock the monitor row so two workers seeing the same failure cannot both open an incident.
            Monitor::whereKey($monitor->id)->lockForUpdate()->first();

            if (Incident::where('monitor_id', $monitor->id)->whereIn('state', IncidentState::openValues())->exists()) {
                return null;
            }

            $circuitId = $monitor->monitorable_type === Circuit::class ? $monitor->monitorable_id : null;

            $incident = Incident::make(['severity' => $circuitId ? IncidentSeverity::Critical : IncidentSeverity::Major]);
            $incident->forceFill([
                'monitor_id' => $monitor->id,
                'circuit_id' => $circuitId,
                'title' => "{$monitor->name} is down",
                'state' => IncidentState::Detected,
                'opened_at' => $monitor->state_changed_at ?? now(),
            ])->save();
            $incident->events()->create(['from_state' => null, 'to_state' => IncidentState::Detected, 'note' => 'Monitor went Down.', 'created_at' => now()]);

            return $incident;
        });

        if ($incident) {
            $this->alerts->opened($incident);
        }

        return $incident;
    }

    /** Move an incident to a new state, or throw a validation error if the state machine forbids it. */
    public function transition(Incident $incident, IncidentState $to, ?User $user = null, ?string $note = null): Incident
    {
        $incident = DB::transaction(function () use ($incident, $to, $user, $note) {
            $locked = Incident::whereKey($incident->id)->lockForUpdate()->firstOrFail();
            $from = $locked->state;

            if (! $from->canMoveTo($to)) {
                throw ValidationException::withMessages(['to' => "An incident that is {$from->value} cannot become {$to->value}."]);
            }
            if ($to === IncidentState::Closed && blank($locked->rfo_summary)) {
                throw ValidationException::withMessages(['to' => 'Write the RFO summary before closing the incident.']);
            }

            $locked->state = $to;
            match ($to) {
                IncidentState::Acknowledged => $locked->acknowledged_at = now(),
                IncidentState::Resolved => $locked->resolved_at = now(),
                IncidentState::Closed => $locked->closed_at = now(),
                default => null,
            };
            $locked->save();
            $locked->events()->create(['from_state' => $from, 'to_state' => $to, 'user_id' => $user?->id, 'note' => $note, 'created_at' => now()]);

            return $locked;
        });

        if ($to === IncidentState::Resolved) {
            $this->alerts->resolved($incident);
        }

        return $incident;
    }
}
