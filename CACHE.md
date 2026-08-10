# Caching — Movie API

## Overview

This project originally shipped **without any application-level caching**. Although Laravel ships with a cache layer configured (`CACHE_STORE=database` and a migrated `cache` table), none of the code in `app/` or `routes/` actually used it. The only cache-adjacent code was framework scaffolding (Breeze's `RateLimiter` and some `throttle` middleware on the password-reset routes).

This document describes the caching feature that was added: **query/response caching for the movie read endpoints**, with automatic invalidation on every write.

---

## What is cached

| Endpoint | Cache key | Content | TTL |
| --- | --- | --- | --- |
| `GET /api/movies` | `movies.all` | Full movie list, eager-loaded with `genre` | 1 hour (3600s) |
| `GET /api/movies/{id}` | `movie.{id}` | Single movie, eager-loaded with `genre` | 1 hour (3600s) |

The list and the single-movie payload are now read from the cache on every request. On the first request the query runs against the database and the result is stored; on subsequent requests the stored value is served — no repeated queries.

As a side improvement, both reads now eager-load the `genre` relationship (`with('genre')`), which also removes a potential N+1 query.

---

## Cache driver & configuration

- **Driver:** `database` — set via `CACHE_STORE=database` in `.env` (the default in `config/cache.php`).
- **Store definition:** `config/cache.php` → `stores.database` (uses the `cache` table from `0001_01_01_000001_create_cache_table.php`).
- **Key prefix:** `CACHE_PREFIX` in `.env` (empty → falls back to `laravel_cache_`).

The implementation is **driver-agnostic**. It works unchanged on `database`, `file`, `memcached`, or `redis`. Upgrading to Redis in production is a **config-only** change:

```ini
CACHE_STORE=redis
```

No application code needs to change.

---

## Invalidation strategy

**Explicit key invalidation**, centralized in the **model layer** via Eloquent events.

The `Movie` model registers `saved` and `deleted` model events in `booted()`. Because every write path in the controllers (`store`, `update`, `destroy`) ultimately persists through Eloquent, these events fire on **every** create, update, or delete. Each event calls `MovieCache::flush()`:

- It clears the shared list key `movies.all`.
- If a specific movie was affected, it also clears `movie.{id}`.

### Why this design?

1. **No stale reads.** A write can never silently leave an outdated entry — the cache is invalidated synchronously with the change.
2. **Single source of truth.** Invalidation logic lives in one place (the model), not sprinkled across controller branches, so future write paths automatically stay consistent.
3. **Driver-agnostic.** Unlike *cache tags*, explicit `Cache::forget()` works on every store — `database`, `file`, `memcached`, and `redis`. (Tags would be a nicer bulk-invalidation option but require Redis or the `array` driver.)
4. **TTL as a safety net.** Even if a write path were ever missed, the 1-hour TTL guarantees the cache self-heals.

---

## Code walkthrough

### 1. `app/Support/MovieCache.php` — key & TTL helper

```php
final class MovieCache
{
    public const TTL = 3600;

    public static function ttl(): int { return self::TTL; }
    public static function allKey(): string { return 'movies.all'; }
    public static function key(int $id): string { return "movie.{$id}"; }

    public static function flush(?int $id = null): void
    {
        Cache::forget(self::allKey());
        if ($id !== null) Cache::forget(self::key($id));
    }
}
```

Centralizes cache keys, TTL, and invalidation so they are referenced consistently instead of being hard-coded as strings throughout the codebase.

### 2. `app/Models/Movie.php` — automatic invalidation

```php
protected static function booted(): void
{
    static::saved(fn (Movie $movie) => MovieCache::flush($movie->getKey()));
    static::deleted(fn (Movie $movie) => MovieCache::flush($movie->getKey()));
}
```

Every create/update (`saved`) and delete (`deleted`) flushes the affected keys.

### 3. `app/Http/Controllers/MovieController.php` — cached reads

```php
public function index()
{
    $movies = Cache::remember(
        MovieCache::allKey(),
        MovieCache::ttl(),
        fn () => Movie::with('genre')->orderBy('id')->get()
    );

    return response()->json(['movies' => $movies]);
}

public function show($id)
{
    try {
        $movie = Cache::remember(
            MovieCache::key($id),
            MovieCache::ttl(),
            fn () => Movie::with('genre')->findOrFail($id)
        );

        return response()->json($movie);
    } catch (ModelNotFoundException $e) {
        // ... existing 404 handling
    }
}
```

`findOrFail` still runs inside the closure, so the existing `ModelNotFoundException` is thrown and caught on a cache miss — the 404 behavior is unchanged.

The `store`, `update`, and `destroy` methods needed **no changes** — invalidation is handled entirely by the model events.

---

## Testing

`tests/Feature/MovieCacheTest.php` (Pest) covers:

- `index` populates `movies.all` on first read.
- `show` populates `movie.{id}` on first read.
- Creating a movie invalidates `movies.all`.
- Updating a movie invalidates both `movies.all` and `movie.{id}`.
- Deleting a movie invalidates both `movies.all` and `movie.{id}`.
- Subsequent `index` reads return consistent, cached data.

Tests isolate caching with `Cache::store('array')->flush()` in `beforeEach`; `phpunit.xml` already sets `CACHE_STORE=array` for the test environment, so the cache is memory-only and fully deterministic.

Run the suite:

```bash
./vendor/bin/pest --testsuite=Feature
```

---

## Trade-offs & future work

**Trade-offs**
- The `array`-cache isolation in tests means invalidation is verified logically rather than against a persistent store, but the logic is driver-agnostic by design.
- TTL (1h) is generous for a demo; a high-traffic production service might lower it and lean more heavily on event-driven invalidation.
- `index`/`show` sit behind `auth:sanctum`, so the cached values are not shared across users; caching still saves repeated DB queries per user.

**Future work**
- **Redis + cache tags:** switch `CACHE_STORE=redis` and tag entries (`movies`, `genres`) to flush whole groups in one call.
- **HTTP response caching:** a small middleware setting `Cache-Control: public, max-age=…` and `ETag`, honoring `If-None-Match` → `304` to save bandwidth on top of the app-level cache.
- **Rate limiting on auth:** add `throttle` to `POST /login` and `POST /register` (backed by the same cache system) to guard public endpoints against abuse.
- **Genres endpoint:** a `GET /genres` cached indefinitely (genres rarely change) would be a good complement.

---

## Agent Prompt — Integrating Caching into the Blog Post

Below is a ready-to-paste prompt for another agent to integrate the caching feature into an existing blog post about this RESTful API project. It references `CACHE.md` as the authoritative source.

---

```
You are helping integrate a newly added caching feature into an existing blog post that
already covers the "Movie API" RESTful project (Laravel + Sanctum + SQLite, JSON REST API).

Authoritative source of truth: read the file `CACHE.md` at the project root before writing.

GOAL
Add a clear, post-appropriate section about the new caching work. Assume the existing post
already explains the API endpoints (movies CRUD, auth, watch-later), the tech stack, and the
data model. Do NOT repeat those fundamentals; build on top of them.

WHAT TO WRITE — a section titled roughly "Performance: Caching the Read Endpoints", covering:

1. BEFORE / AFTER framing
   - Before: no application-level caching existed; every GET /movies and GET /movies/{id}
     hit the database each request.
   - After: both reads are served from the cache (1-hour TTL) with genre eager-loaded.

2. Cached endpoints & keys (table)
   - GET /api/movies -> key movies.all
   - GET /api/movies/{id} -> key movie.{id}

3. Architecture decisions (explain WHY, in plain language):
   - Cache driver = database (config-only upgrade to Redis later).
   - Invalidation centralized in the Movie model via Eloquent `saved`/`deleted` events,
     so every write auto-flushes the cache -> "no stale reads".
   - Why explicit key invalidation (driver-agnostic) instead of cache tags.
   - Why a 1-hour TTL as a safety net.

4. Concrete code to include (short excerpts, 3-8 lines each):
   - App\Support\MovieCache (key/TTL/flush helper)
   - Movie::booted() event hooks
   - MovieController@index and @show using Cache::remember

5. Testing & verification
   - Mention tests/Feature/MovieCacheTest.php (Pest) covering population + invalidation,
     and the array-driver isolation.

6. Future work teaser (1 short paragraph, optional):
   - Redis + cache tags, HTTP response caching (ETag/Cache-Control/304), rate limiting on auth.

STYLE / FORMAT
   - Match the existing post's tone, voice, and heading style. Keep it reader-friendly for a
     developer-audience portfolio piece.
   - Use concise code blocks and short bullets; do not dump entire files.
   - Position the section after the existing API/data-model content and before any concluding
     "lessons learned" / "future work" content.
   - Where useful, link/mention CACHE.md for deeper detail.
   - Keep the whole addition between ~300 and ~600 words of prose (excluding code blocks).

When done, report the exact heading you added and where it sits in the article outline.
```

---
