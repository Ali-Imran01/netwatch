<?php

namespace App\Probes;

use App\Enums\MonitorType;

class ProbeFactory
{
    public static function for(MonitorType $type): Probe
    {
        return match ($type) {
            MonitorType::Ping => new PingProbe,
            MonitorType::Tcp => new TcpProbe,
            MonitorType::Http => new HttpProbe,
            MonitorType::Dns => new DnsProbe,
            MonitorType::Simulator => new SimulatorProbe,
        };
    }
}
