<?php

namespace App\Enums;

/** States and legal moves are documented in docs/incident-states.md. */
enum IncidentState: string
{
    case Detected = 'detected';
    case Acknowledged = 'acknowledged';
    case Investigating = 'investigating';
    case Escalated = 'escalated';
    case Monitoring = 'monitoring';
    case Resolved = 'resolved';
    case Closed = 'closed';

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Detected => [self::Acknowledged, self::Resolved],
            self::Acknowledged => [self::Investigating, self::Resolved],
            self::Investigating => [self::Escalated, self::Resolved],
            self::Escalated => [self::Monitoring],
            self::Monitoring => [self::Resolved],
            self::Resolved => [self::Closed],
            self::Closed => [],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->allowedNext(), true);
    }

    /** Still needs attention: not yet resolved. */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Resolved, self::Closed], true);
    }

    /** States a recovering monitor resolves automatically. Escalated waits for the provider; Monitoring for a person to confirm. */
    public function autoResolves(): bool
    {
        return in_array($this, [self::Detected, self::Acknowledged, self::Investigating], true);
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return array_map(fn (self $s) => $s->value, array_filter(self::cases(), fn (self $s) => $s->isOpen()));
    }
}
