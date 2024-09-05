<?php
    
use Tests\TestCase;
use App\Models\User;
use App\Models\Movie;
use App\Models\Genre;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Genre::factory()->count(3)->create(); // Create some genres first
    $this->movies = Movie::factory()->count(3)->create();
});

test('user can add a movie to database', function () {
    Sanctum::actingAs($this->user);
    
    $genre = Genre::first();
    $movieData = [
        'title' => 'Test Movie',
        'description' => 'This is a test movie description',
        'release_date' => '2023-01-01',
        'genre_id' => $genre->id,
    ];

    $response = $this->postJson('/api/movies', $movieData);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'id',
            'title',
            'description',
            'release_date',
            'genre_id',
            'created_at',
            'updated_at',
        ]);

    $this->assertDatabaseHas('movies', [
        'title' => 'Test Movie',
        'description' => 'This is a test movie description',
        'release_date' => '2023-01-01',
        'genre_id' => $genre->id,
    ]);
});

test('user can remove a movie from database', function () {
    Sanctum::actingAs($this->user);
    
    $movie = $this->movies->first();

    $response = $this->deleteJson("/api/movies/{$movie->id}");

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Movie successfully deleted',
            'data' => [
                'deleted' => true
            ]
        ]);

    $this->assertDatabaseMissing('movies', [
        'id' => $movie->id,
    ]);

    $this->assertDatabaseCount('movies', 2);
});

test('user can read a movie from database', function () {
    Sanctum::actingAs($this->user);
    
    $movie = $this->movies->first();

    $response = $this->getJson("/api/movies/{$movie->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'id',
            'title',
            'description',
            'release_date',
            'genre_id',
            'created_at',
            'updated_at',
        ])
        ->assertJson([
            'id' => $movie->id,
            'title' => $movie->title,
            'description' => $movie->description,
            'release_date' => $movie->release_date,
            'genre_id' => $movie->genre_id,
        ]);
});

test('user can update a movie from database', function () {
    $actingAs = Sanctum::actingAs($this->user);
    
    $movie = $this->movies->first();
    $updatedData = [
        'title' => 'Updated Movie Title',
        'description' => 'Updated movie description',
        'release_date' => '2023-01-01',
        'genre_id' => $movie->genre_id,
    ];

    $response = $this->putJson("/api/movies/{$movie->id}", $updatedData);

    $response->assertStatus(200)
        ->assertJson([
            'movie' => [
                'id' => $movie->id,
                'title' => $updatedData['title'],
                'description' => $updatedData['description'],
                'release_date' => $updatedData['release_date'],
                'genre_id' => $updatedData['genre_id'],
            ]
        ]);

    // Add this assertion to check the database
    $this->assertDatabaseHas('movies', $updatedData);
});