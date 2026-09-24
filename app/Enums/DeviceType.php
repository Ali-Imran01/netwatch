<?php

namespace App\Enums;

enum DeviceType: string
{
    case Router = 'router';
    case Switch = 'switch';
    case Firewall = 'firewall';
    case Server = 'server';
    case AccessPoint = 'access_point';
    case Other = 'other';
}
