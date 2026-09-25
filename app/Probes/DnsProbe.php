<?php

namespace App\Probes;

use App\Models\Monitor;

/** Up means the name resolves to at least one A or AAAA record via the system resolver. PHP has no per-query timeout, so timeout_ms is not enforced here. */
class DnsProbe implements Probe
{
    public function run(Monitor $monitor): ProbeResult
    {
        $started = microtime(true);
        $records = @dns_get_record($monitor->target, DNS_A | DNS_AAAA) ?: [];

        return $records
            ? new ProbeResult(true, (int) round((microtime(true) - $started) * 1000), count($records).' record(s)')
            : new ProbeResult(false, null, 'No A/AAAA records');
    }
}
