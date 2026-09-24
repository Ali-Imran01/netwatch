<?php

namespace Database\Factories;

use App\Enums\DeviceType;
use App\Models\Device;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Device> */
class DeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'name' => fake()->unique()->bothify('rtr-??-##'),
            'type' => fake()->randomElement(DeviceType::cases()),
            'vendor' => fake()->randomElement(['Cisco', 'Juniper', 'MikroTik', 'Fortinet']),
            'model' => fake()->bothify('??-####'),
            'serial' => strtoupper(fake()->unique()->bothify('SN##########')),
        ];
    }
}
