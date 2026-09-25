<?php

namespace App\Enums;

enum IncidentSeverity: string
{
    case Critical = 'critical';
    case Major = 'major';
    case Minor = 'minor';
}
