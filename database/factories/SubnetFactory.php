<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\Subnet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subnet> */
class SubnetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'vlan_id' => null,
            'cidr' => '10.'.fake()->unique()->numberBetween(0, 255).'.'.fake()->numberBetween(0, 255).'.0/24',
            'description' => fake()->sentence(3),
            'gateway' => null,
        ];
    }
}
