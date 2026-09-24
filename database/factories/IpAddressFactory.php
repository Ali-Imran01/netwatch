<?php

namespace Database\Factories;

use App\Enums\IpStatus;
use App\Models\IpAddress;
use App\Models\Subnet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IpAddress> */
class IpAddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subnet_id' => Subnet::factory(),
            // Placeholder; tests should pass an address inside the subnet they create.
            'address' => '10.0.0.'.fake()->unique()->numberBetween(1, 254),
            'status' => IpStatus::Free,
            'device_id' => null,
            'dns_name' => null,
        ];
    }
}
