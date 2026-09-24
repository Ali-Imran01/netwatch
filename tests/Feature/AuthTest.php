<?php

use App\Enums\UserRole;
use App\Models\User;

it('logs in a seeded user with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'admin@netwatch.test',
        'password' => 'password',
        'role' => UserRole::Admin,
    ]);

    $response = $this->withHeader('Referer', 'http://localhost:5173')->postJson('/api/login', [
        'email' => 'admin@netwatch.test',
        'password' => 'password',
    ]);

    $response->assertOk();
    $response->assertJsonPath('user.id', $user->id);
    $this->assertAuthenticatedAs($user);
});

it('rejects an incorrect password', function () {
    User::factory()->create([
        'email' => 'admin@netwatch.test',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'admin@netwatch.test',
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable();
    $this->assertGuest();
});

it('rejects unauthenticated access to /api/user', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});

it('returns the authenticated user from /api/user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/user');

    $response->assertOk();
    $response->assertJsonPath('user.id', $user->id);
});
