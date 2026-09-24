<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@netwatch.test',
            'password' => 'password',
            'role' => UserRole::Admin,
        ]);

        User::factory()->create([
            'name' => 'Engineer',
            'email' => 'engineer@netwatch.test',
            'password' => 'password',
            'role' => UserRole::Engineer,
        ]);

        User::factory()->create([
            'name' => 'Viewer',
            'email' => 'viewer@netwatch.test',
            'password' => 'password',
            'role' => UserRole::Viewer,
        ]);

        $this->call(InventorySeeder::class);
    }
}
