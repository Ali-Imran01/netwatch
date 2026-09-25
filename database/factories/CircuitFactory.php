<?php

namespace Database\Factories;

use App\Enums\CircuitType;
use App\Models\Circuit;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Circuit> */
class CircuitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            'circuit_ref' => strtoupper(fake()->unique()->bothify('CKT-####-??')),
            'name' => fake()->unique()->bothify('Circuit ??-##'),
            'type' => fake()->randomElement(CircuitType::cases()),
            'bandwidth_mbps' => fake()->randomElement([100, 500, 1000, 10000]),
            'sla_target' => 99.9,
        ];
    }
}
