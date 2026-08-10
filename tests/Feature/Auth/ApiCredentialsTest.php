<?php

use App\Models\User;

test('login rejects credentials sent in the URL query string', function () {
    User::factory()->create([
        'email' => 'luis@libe.dev',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/login?email=luis@libe.dev&password=password');

    $response->assertStatus(422)
        ->assertJsonMissingPath('access_token')
        ->assertJsonFragment([
            'message' => 'Credentials must be sent in the request body, not the URL query string.',
        ]);
});

test('login accepts credentials sent in a JSON body', function () {
    User::factory()->create([
        'email' => 'luis@libe.dev',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'luis@libe.dev',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['access_token', 'token_type']);
});

test('login accepts credentials sent in a form-encoded body', function () {
    User::factory()->create([
        'email' => 'luis@libe.dev',
        'password' => 'password',
    ]);

    $response = $this->post('/api/login', [
        'email' => 'luis@libe.dev',
        'password' => 'password',
    ], ['Accept' => 'application/json', 'Content-Type' => 'application/x-www-form-urlencoded']);

    $response->assertOk()
        ->assertJsonStructure(['access_token', 'token_type']);
});

test('register rejects credentials sent in the URL query string', function () {
    $response = $this->postJson('/api/register?name=User&email=user@example.com&password=password123&password_confirmation=password123');

    $response->assertStatus(422)
        ->assertJsonMissingPath('access_token')
        ->assertJsonFragment([
            'message' => 'Credentials must be sent in the request body, not the URL query string.',
        ]);
});
