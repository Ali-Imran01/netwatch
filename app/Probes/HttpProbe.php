<?php

namespace App\Probes;

use App\Models\Monitor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Up means a 2xx or 3xx reply. Redirects are not followed, so a redirecting site is judged on its own response. */
class HttpProbe implements Probe
{
    public function run(Monitor $monitor): ProbeResult
    {
        $started = microtime(true);

        try {
            $response = Http::timeout(max(1, (int) ceil($monitor->timeout_ms / 1000)))->withoutRedirecting()->get($monitor->target);
        } catch (ConnectionException $e) {
            return new ProbeResult(false, null, mb_substr($e->getMessage(), 0, 200));
        }

        return new ProbeResult(
            $response->status() < 400,
            (int) round((microtime(true) - $started) * 1000),
            "HTTP {$response->status()}",
        );
    }
}
