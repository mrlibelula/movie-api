<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('authenticated user is limited to 30 api requests per minute', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    for ($i = 0; $i < 30; $i++) {
        $this->getJson('/api/movies')->assertOk();
    }

    $this->getJson('/api/movies')->assertStatus(429);
});

test('unauthenticated requests to protected api routes are rejected', function () {
    $this->getJson('/api/movies')->assertStatus(401);
    $this->getJson('/api/watch-later')->assertStatus(401);
});

test('api login is rate limited after 5 attempts per minute', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/login', [
            'email' => 'someone@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    $this->postJson('/api/login', [
        'email' => 'someone@example.com',
        'password' => 'wrong-password',
    ])->assertStatus(429);
});

test('api register is rate limited per ip', function () {
    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/register', [
            'name' => 'User '.$i,
            'email' => 'user'.$i.'@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertOk();
    }

    $this->postJson('/api/register', [
        'name' => 'User 4',
        'email' => 'user4@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(429);
});

test('security headers are present on responses', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/movies')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'no-referrer');
});
