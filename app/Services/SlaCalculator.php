<?php

namespace App\Services;

use App\Models\CheckRollup;
use App\Models\Circuit;
use App\Models\MaintenanceWindow;
use Carbon\CarbonInterface;

/**
 * Availability of a circuit over a period, from the 5-minute rollups of every monitor linked to it.
 *
 * A bucket that overlaps a maintenance window covering its monitor (the window's circuit, or the monitor itself)
 * is left out entirely, so planned work counts neither for nor against the circuit. Granularity is therefore
 * 5 minutes: a window edge inside a bucket excludes that whole bucket.
 */
class SlaCalculator
{
    private const BUCKET = 300;

    public function forCircuit(Circuit $circuit, CarbonInterface $from, CarbonInterface $to): array
    {
        $monitors = $circuit->monitors()->get()->keyBy('id');

        $windows = MaintenanceWindow::where('starts_at', '<', $to)->where('ends_at', '>', $from)
            ->where(fn ($q) => $q->where('circuit_id', $circuit->id)->orWhereIn('monitor_id', $monitors->keys()))
            ->get();

        $checks = $failures = $excluded = 0;

        CheckRollup::whereIn('monitor_id', $monitors->keys())->where('bucket_size', self::BUCKET)
            ->where('bucket_start', '>=', $from)->where('bucket_start', '<', $to)
            ->each(function (CheckRollup $r) use ($monitors, $windows, &$checks, &$failures, &$excluded) {
                $end = $r->bucket_start->copy()->addSeconds(self::BUCKET);
                $monitor = $monitors[$r->monitor_id];

                $silenced = $windows->contains(fn (MaintenanceWindow $w) => $w->covers($monitor) && $w->starts_at < $end && $w->ends_at > $r->bucket_start);
                if ($silenced) {
                    $excluded += $r->checks;

                    return;
                }
                $checks += $r->checks;
                $failures += $r->failures;
            });

        $availability = $checks > 0 ? round(($checks - $failures) / $checks * 100, 3) : null;

        return [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'availability' => $availability,
            'target' => $circuit->sla_target,
            'meets_target' => $availability === null ? null : $availability >= $circuit->sla_target,
            'checks' => $checks,
            'failures' => $failures,
            'excluded_checks' => $excluded,
            'monitors' => $monitors->count(),
        ];
    }
}
