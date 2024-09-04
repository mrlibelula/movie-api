<?php

use App\Models\User;
use App\Models\Movie;
use App\Models\Genre;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Genre::factory()->count(3)->create(); // Create some genres first
    $this->movies = Movie::factory()->count(3)->create();
});

test('user can add a movie to watch later list', function () {
    Sanctum::actingAs($this->user);
    
    $movie = $this->movies->first();

    $response = $this->postJson("/api/movies/{$movie->id}/watch-later");

    $response->assertStatus(200)
        ->assertJson([
            'message' => "The movie \"{$movie->title}\" has been added to your watch later list."
        ]);

    $this->assertDatabaseHas('movie_user', [
        'user_id' => $this->user->id,
        'movie_id' => $movie->id,
    ]);
});

test('user cannot add the same movie to watch later list twice', function () {
    Sanctum::actingAs($this->user);
    
    $movie = $this->movies->first();
    $this->user->watchLater()->attach($movie->id);

    $response = $this->postJson("/api/movies/{$movie->id}/watch-later");

    $response->assertStatus(409)
        ->assertJson([
            'message' => "The movie \"{$movie->title}\" is already in your watch later list."
        ]);
});

test('user can remove a movie from watch later list', function () {
    Sanctum::actingAs($this->user);
    
    $movie = $this->movies->first();
    $this->user->watchLater()->attach($movie->id);

    $response = $this->deleteJson("/api/movies/{$movie->id}/watch-later");

    $response->assertStatus(200)
        ->assertJson([
            'message' => "The movie \"{$movie->title}\" has been removed from your watch later list."
        ]);

    $this->assertDatabaseMissing('movie_user', [
        'user_id' => $this->user->id,
        'movie_id' => $movie->id,
    ]);
});

test('user cannot remove a movie that is not in their watch later list', function () {
    Sanctum::actingAs($this->user);
    
    $movie = $this->movies->first();

    $response = $this->deleteJson("/api/movies/{$movie->id}/watch-later");

    $response->assertStatus(404)
        ->assertJson([
            'message' => "The movie \"{$movie->title}\" is not in your watch later list."
        ]);
});

test('user can retrieve their watch later list', function () {
    Sanctum::actingAs($this->user);
    
    $this->user->watchLater()->attach($this->movies->pluck('id'));

    $response = $this->getJson("/api/watch-later");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                'user' => ['id', 'name', 'email'],
                'watch_later' => [
                    'count',
                    'movies' => [
                        '*' => [
                            'id',
                            'title',
                            'description',
                            'release_date',
                            'genre' => [
                                'id',
                                'name',
                            ],
                        ],
                    ],
                ],
            ],
        ])
        ->assertJsonCount(3, 'data.watch_later.movies')
        ->assertJsonPath('data.watch_later.count', 3);
});

test('unauthenticated user cannot access watch later endpoints', function () {
    $movie = $this->movies->first();

    $this->postJson("/api/movies/{$movie->id}/watch-later")->assertStatus(401);
    $this->deleteJson("/api/movies/{$movie->id}/watch-later")->assertStatus(401);
    $this->getJson("/api/watch-later")->assertStatus(401);
});
