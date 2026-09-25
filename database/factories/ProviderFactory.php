<?php

namespace Database\Factories;

use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Provider> */
class ProviderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Carrier',
            'noc_email' => fake()->unique()->safeEmail(),
            'noc_phone' => '+60 3 5550 '.fake()->numerify('####'),
        ];
    }
}
