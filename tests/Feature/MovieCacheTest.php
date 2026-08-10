<?php

use App\Models\Genre;
use App\Models\Movie;
use App\Models\User;
use App\Support\MovieCache;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Cache::store('array')->flush();

    $this->user = User::factory()->create();
    Genre::factory()->count(3)->create();
    $this->movies = Movie::factory()->count(3)->create();
});

test('index populates the movies.all cache on first read', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/movies')->assertStatus(200);

    expect(Cache::has(MovieCache::allKey()))->toBeTrue();
});

test('show populates the movie cache on first read', function () {
    Sanctum::actingAs($this->user);

    $movie = $this->movies->first();

    $this->getJson("/api/movies/{$movie->id}")->assertStatus(200);

    expect(Cache::has(MovieCache::key($movie->id)))->toBeTrue();
});

test('movies.all cache is invalidated when a movie is created', function () {
    $movie = Movie::factory()->create();

    expect(Cache::has(MovieCache::allKey()))->toBeFalse();
    expect($movie->exists)->toBeTrue();

    Cache::put(MovieCache::allKey(), 'stale');
    Sanctum::actingAs($this->user);

    $this->postJson('/api/movies', [
        'title' => 'Fresh Movie',
        'description' => 'A brand new movie',
        'release_date' => '2024-01-01',
        'genre_id' => Genre::first()->id,
    ])->assertStatus(201);

    expect(Cache::has(MovieCache::allKey()))->toBeFalse();
});

test('movie cache is invalidated when a movie is updated', function () {
    $movie = $this->movies->first();
    Cache::put(MovieCache::key($movie->id), 'stale');
    Cache::put(MovieCache::allKey(), 'stale');

    Sanctum::actingAs($this->user);

    $this->putJson("/api/movies/{$movie->id}", [
        'title' => 'Updated Title',
    ])->assertStatus(200);

    expect(Cache::has(MovieCache::key($movie->id)))->toBeFalse();
    expect(Cache::has(MovieCache::allKey()))->toBeFalse();
});

test('movie cache is invalidated when a movie is deleted', function () {
    $movie = $this->movies->first();
    $id = $movie->id;
    Cache::put(MovieCache::key($id), 'stale');
    Cache::put(MovieCache::allKey(), 'stale');

    Sanctum::actingAs($this->user);

    $this->deleteJson("/api/movies/{$id}")->assertStatus(200);

    expect(Cache::has(MovieCache::key($id)))->toBeFalse();
    expect(Cache::has(MovieCache::allKey()))->toBeFalse();
});

test('subsequent index reads come from cache', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/movies');
    $first = Cache::get(MovieCache::allKey());
    $second = Cache::get(MovieCache::allKey());

    expect($first)->not->toBeNull();
    expect($first)->toBe($second);
    expect($second)->toHaveCount(3);
});
