<?php

namespace App\Enums;

enum MonitorState: string
{
    case Unknown = 'unknown';
    case Up = 'up';
    case Down = 'down';
}
