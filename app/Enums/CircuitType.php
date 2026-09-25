<?php

namespace App\Enums;

enum CircuitType: string
{
    case Iplc = 'iplc';
    case Iepl = 'iepl';
    case Dia = 'dia';
    case Mpls = 'mpls';
    case Other = 'other';
}
