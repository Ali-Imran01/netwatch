<?php

namespace Database\Factories;

use App\Models\MaintenanceWindow;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaintenanceWindow> */
class MaintenanceWindowFactory extends Factory
{
    public function definition(): array
    {
        return [
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(4),
            'provider_ref' => fake()->bothify('CHG-######'),
        ];
    }
}
