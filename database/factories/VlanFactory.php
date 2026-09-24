<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\Vlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vlan> */
class VlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'vid' => fake()->unique()->numberBetween(2, 4094),
            'name' => fake()->unique()->word(),
        ];
    }
}
