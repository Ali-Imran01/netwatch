<?php

namespace App\Enums;

enum MonitorType: string
{
    case Ping = 'ping';
    case Tcp = 'tcp';
    case Http = 'http';
    case Dns = 'dns';
}
