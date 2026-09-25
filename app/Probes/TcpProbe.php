<?php

namespace App\Probes;

use App\Models\Monitor;

class TcpProbe implements Probe
{
    public function run(Monitor $monitor): ProbeResult
    {
        $started = microtime(true);
        $host = filter_var($monitor->target, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? "[{$monitor->target}]" : $monitor->target;
        $socket = @stream_socket_client("tcp://{$host}:{$monitor->port}", $errno, $error, $monitor->timeout_ms / 1000);

        if ($socket === false) {
            return new ProbeResult(false, null, $error !== '' ? $error : 'Connection failed');
        }
        fclose($socket);

        return new ProbeResult(true, (int) round((microtime(true) - $started) * 1000));
    }
}
