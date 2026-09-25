<?php

namespace App\Services;

use App\Models\CheckResult;
use App\Models\Monitor;
use App\Probes\ProbeFactory;
use App\Probes\ProbeResult;
use Throwable;

class CheckRunner
{
    /** Run one probe, store the raw result and refresh the monitor's latest-result columns. */
    public function run(Monitor $monitor): CheckResult
    {
        $checkedAt = now();

        try {
            $result = ProbeFactory::for($monitor->type)->run($monitor);
        } catch (Throwable $e) {
            report($e);
            $result = new ProbeResult(false, null, 'Probe error: '.mb_substr($e->getMessage(), 0, 200));
        }

        $stored = CheckResult::create([
            'monitor_id' => $monitor->id,
            'checked_at' => $checkedAt,
            'success' => $result->success,
            'latency_ms' => $result->latencyMs,
            'detail' => $result->detail,
        ]);

        // Query-builder update: latest-result bookkeeping is not an audited edit.
        Monitor::whereKey($monitor->id)->update([
            'last_checked_at' => $checkedAt,
            'last_success' => $result->success,
            'last_latency_ms' => $result->latencyMs,
        ]);

        return $stored;
    }
}
