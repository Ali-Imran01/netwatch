<?php

namespace App\Probes;

use App\Models\Monitor;
use Illuminate\Support\Facades\Process;

/** Shells out to the system ping (iputils on the app image). The target is validated on write and passed as an argv element, never through a shell. */
class PingProbe implements Probe
{
    public function run(Monitor $monitor): ProbeResult
    {
        $seconds = max(1, (int) ceil($monitor->timeout_ms / 1000));
        $started = microtime(true);

        $result = Process::timeout($seconds + 2)->run(['ping', '-c', '1', '-W', (string) $seconds, $monitor->target]);

        if ($result->failed()) {
            return new ProbeResult(false, null, 'No reply');
        }

        // Prefer the RTT ping reports; fall back to wall time if the output is unparseable.
        $rtt = preg_match('/time[=<]([\d.]+)\s*ms/', $result->output(), $m) ? (float) $m[1] : (microtime(true) - $started) * 1000;

        return new ProbeResult(true, (int) round($rtt));
    }
}
