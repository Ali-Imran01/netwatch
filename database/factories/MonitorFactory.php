<?php

namespace Database\Factories;

use App\Enums\MonitorType;
use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Monitor> */
class MonitorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->bothify('mon-??-##'),
            'type' => MonitorType::Ping,
            'target' => fake()->unique()->ipv4(),
            'interval_s' => 30,
            'timeout_ms' => 2000,
        ];
    }
}
