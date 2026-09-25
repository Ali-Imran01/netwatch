<?php

namespace App\Probes;

use App\Models\Monitor;

interface Probe
{
    /** Run one check. Must not throw for an unreachable target; return a failed result instead. */
    public function run(Monitor $monitor): ProbeResult;
}
