<?php

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Site> */
class SiteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city().' POP',
            'code' => strtoupper(fake()->unique()->lexify('???-##')),
            'city' => fake()->city(),
            'country' => 'MY',
            'lat' => fake()->latitude(1, 7),
            'lng' => fake()->longitude(100, 119),
        ];
    }
}
