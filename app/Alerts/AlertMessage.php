<?php

namespace App\Alerts;

use App\Models\Incident;

/** Plain-text wording shared by every channel. */
class AlertMessage
{
    public static function subject(Incident $incident, string $kind): string
    {
        return ($kind === 'opened' ? 'INCIDENT' : 'RESOLVED')." #{$incident->id}: {$incident->title}";
    }

    public static function body(Incident $incident, string $kind): string
    {
        $incident->loadMissing(['monitor', 'circuit.provider']);
        $m = $incident->monitor;

        $lines = [
            self::subject($incident, $kind),
            "Severity: {$incident->severity->value}",
            "Monitor: {$m->name} ({$m->type->value} {$m->target}".($m->port ? ":{$m->port}" : '').')',
        ];
        if ($incident->circuit) {
            $lines[] = "Circuit: {$incident->circuit->name} ({$incident->circuit->provider->name}, ref {$incident->circuit->circuit_ref})";
        }
        $lines[] = 'Detected: '.$incident->opened_at->utc()->format('Y-m-d H:i:s').' UTC';
        if ($kind === 'resolved' && $incident->resolved_at) {
            $lines[] = 'Downtime: '.self::duration($incident->timeToResolve());
        }

        return implode("\n", $lines);
    }

    public static function duration(?int $seconds): string
    {
        $seconds ??= 0;

        return match (true) {
            $seconds >= 3600 => intdiv($seconds, 3600).'h '.intdiv($seconds % 3600, 60).'m',
            $seconds >= 60 => intdiv($seconds, 60).'m '.($seconds % 60).'s',
            default => "{$seconds}s",
        };
    }
}
