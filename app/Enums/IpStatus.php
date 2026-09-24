<?php

namespace App\Enums;

enum IpStatus: string
{
    case Free = 'free';
    case Reserved = 'reserved';
    case Assigned = 'assigned';
}
