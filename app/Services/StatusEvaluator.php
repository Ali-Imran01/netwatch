<?php

namespace App\Services;

use App\Enums\MonitorState;
use App\Events\MonitorChecked;
use App\Models\CheckResult;
use App\Models\Monitor;
use Illuminate\Support\Facades\DB;

/**
 * Flap-protected state machine over the stream of check results. Also records the latest result on the monitor.
 *
 * - Down after `down_after` consecutive failures (a single dropped probe changes nothing).
 * - Back Up after `up_after` consecutive passes when recovering from Down.
 * - After each result, IncidentService opens or auto-resolves incidents.
 * - A monitor with no history goes Up on its first pass; failures still need `down_after` to count as Down.
 */
class StatusEvaluator
{
    public function __construct(private IncidentService $incidents) {}

    public function apply(Monitor $monitor, CheckResult $result): Monitor
    {
        $stateChanged = false;

        // Row lock so a "Run now" racing a scheduled check cannot lose a counter update.
        DB::transaction(function () use ($monitor, $result, &$stateChanged) {
            $m = Monitor::whereKey($monitor->id)->lockForUpdate()->firstOrFail();
            $success = $result->success;

            $m->last_checked_at = $result->checked_at;
            $m->last_success = $success;
            $m->last_latency_ms = $result->latency_ms;
            $m->consecutive_failures = $success ? 0 : $m->consecutive_failures + 1;
            $m->consecutive_successes = $success ? $m->consecutive_successes + 1 : 0;

            $next = match (true) {
                $m->state === MonitorState::Up && $m->consecutive_failures >= $m->down_after => MonitorState::Down,
                $m->state === MonitorState::Unknown && $success => MonitorState::Up,
                $m->state === MonitorState::Unknown && $m->consecutive_failures >= $m->down_after => MonitorState::Down,
                $m->state === MonitorState::Down && $m->consecutive_successes >= $m->up_after => MonitorState::Up,
                default => $m->state,
            };

            if ($next !== $m->state) {
                $m->state = $next;
                $m->state_changed_at = $result->checked_at;
                $stateChanged = true;
            }

            // Bookkeeping, not an audited edit.
            $m->saveQuietly();
            $monitor->setRawAttributes($m->getAttributes(), true);
        });

        MonitorChecked::dispatch($monitor, $stateChanged);

        // A failure here (say, Redis down while queueing an alert) must not fail the check that was already recorded.
        try {
            $this->incidents->sync($monitor, $stateChanged);
        } catch (\Throwable $e) {
            report($e);
        }

        return $monitor;
    }
}
