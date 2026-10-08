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
use Illuminate\Support\Facades\Gate;
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
                IncidentState::Acknowledged => $this->claim($locked, $user),
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

    /** Whoever acknowledges an unowned incident takes it. A Telegram ack has no user, so it stays unassigned. */
    private function claim(Incident $incident, ?User $user): void
    {
        $incident->acknowledged_at = now();
        $incident->assignee_id ??= $user?->id;
    }

    /** Add a free-text note to the timeline without changing the state. */
    public function addNote(Incident $incident, User $user, string $note): Incident
    {
        return $this->recordOnTimeline($incident, 'note', $user, $note);
    }

    /** Give the incident to an engineer or admin, or take it back to unassigned with null. */
    public function assign(Incident $incident, ?User $assignee, User $actor): Incident
    {
        // Whoever may update incidents may own them; the policy already says who that is.
        if ($assignee && Gate::forUser($assignee)->denies('update', $incident)) {
            throw ValidationException::withMessages(['assignee_id' => 'Only an admin or an engineer can own an incident.']);
        }

        return $this->recordOnTimeline($incident, 'assignment', $actor, $assignee ? "Assigned to {$assignee->name}." : 'Unassigned.', function (Incident $locked) use ($assignee) {
            $locked->assignee_id = $assignee?->id;
        }, fn (Incident $locked) => $locked->assignee_id === $assignee?->id);
    }

    /**
     * A closed incident is a record and takes no more entries. The event keeps the state the incident was in,
     * so the timeline reads in order; $skip lets a no-op (assigning to the current owner) write nothing.
     */
    private function recordOnTimeline(Incident $incident, string $type, User $user, string $note, ?callable $change = null, ?callable $skip = null): Incident
    {
        return DB::transaction(function () use ($incident, $type, $user, $note, $change, $skip) {
            $locked = Incident::whereKey($incident->id)->lockForUpdate()->firstOrFail();

            if ($locked->state === IncidentState::Closed) {
                throw ValidationException::withMessages(['state' => 'A closed incident cannot be edited.']);
            }
            if ($skip && $skip($locked)) {
                return $locked;
            }

            if ($change) {
                $change($locked);
                $locked->save();
            }
            $locked->events()->create(['type' => $type, 'from_state' => null, 'to_state' => $locked->state, 'user_id' => $user->id, 'note' => $note, 'created_at' => now()]);

            return $locked;
        });
    }
}
