<?php

namespace App\Probes;

final class ProbeResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?int $latencyMs = null,
        public readonly ?string $detail = null,
    ) {}
}
