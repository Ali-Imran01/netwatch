<?php

namespace App\Probes;

use App\Models\Monitor;

/**
 * Network-free probe for the public demo: results follow a scripted scenario, chosen with the monitor's target.
 * Everything is a pure function of the clock and the monitor id, so it needs no state and every viewer of the
 * demo sees the same story. Each monitor is shifted within a 30-minute cycle so they do not all misbehave together.
 */
class SimulatorProbe implements Probe
{
    private const CYCLE = 1800;

    /** scenario => what it shows off */
    public const SCENARIOS = [
        'stable' => 'Always up, low latency',
        'flapping' => 'Two failed checks then a pass, repeating: never enough to go Down (flap protection)',
        'latency_spike' => 'Normal latency with a 5-minute spike to ~300 ms every 30 minutes',
        'outage_cycle' => 'A 6-minute outage every 30 minutes that recovers by itself (opens and auto-resolves an incident)',
    ];

    public function run(Monitor $monitor): ProbeResult
    {
        return $this->at($monitor, now()->timestamp);
    }

    public function at(Monitor $monitor, int $timestamp): ProbeResult
    {
        $slot = intdiv($timestamp, max(30, $monitor->interval_s));
        $position = ($timestamp + ($monitor->id * 137)) % self::CYCLE;
        $jitter = crc32("{$monitor->id}:{$slot}") % 7;

        return match ($monitor->target) {
            'flapping' => $slot % 3 === 0 ? new ProbeResult(true, 12 + $jitter) : new ProbeResult(false, null, 'Simulated packet loss'),
            'latency_spike' => new ProbeResult(true, $position < 300 ? 250 + $jitter * 15 : 18 + $jitter),
            'outage_cycle' => $position < 360 ? new ProbeResult(false, null, 'Simulated outage') : new ProbeResult(true, 22 + $jitter),
            default => new ProbeResult(true, 8 + $jitter),
        };
    }
}
