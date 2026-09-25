<?php

namespace Database\Factories;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentState;
use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Incident> */
class IncidentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'monitor_id' => Monitor::factory(),
            'title' => fake()->sentence(4),
            'state' => IncidentState::Detected,
            'severity' => IncidentSeverity::Major,
            'opened_at' => now(),
        ];
    }
}
