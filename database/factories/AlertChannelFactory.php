<?php

namespace Database\Factories;

use App\Models\AlertChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AlertChannel> */
class AlertChannelFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->words(2, true), 'type' => 'telegram', 'target' => (string) fake()->numberBetween(100000, 999999), 'enabled' => true];
    }

    public function email(): static
    {
        return $this->state(fn () => ['type' => 'email', 'target' => fake()->unique()->safeEmail()]);
    }
}
