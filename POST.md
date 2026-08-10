# A Small API Done Right: Validation, Status Codes, and Tests

Small APIs are where fundamentals show. There's no architecture to hide behind, just endpoints, and the question of whether each one behaves correctly when the input is wrong, the user is unauthorized, or the same request arrives twice. This movie database API is deliberately small so those details are the whole story.

> **Quick Look**
>
> | | |
> |---|---|
> | **Goal** | A clean RESTful API: token auth, correct HTTP semantics, real test coverage |
> | **Stack** | Laravel • PHP 8.2+ • Sanctum • Pest • SQLite (dev) / MySQL (prod) |
> | **Links** | [GitHub repo](https://github.com/mrlibelula/movie-api) • [Postman workspace](https://web.postman.co/workspace/7c896a09-836b-4117-8896-f381c94c4dfe/request/7430789-05750611-610a-4955-8e04-f284bbb9d38f) |

## The Surface

Resource-oriented endpoints, verbs where they belong:

```http
# Authentication (Public)
POST   /api/register          # Create account & get token
POST   /api/login             # Authenticate & get token

# Movies (Protected)
GET    /api/movies
POST   /api/movies
GET    /api/movies/{id}
PUT    /api/movies/{id}
DELETE /api/movies/{id}

# Watch Later (Protected)
POST   /api/movies/{id}/watch-later
DELETE /api/movies/{id}/watch-later
GET    /api/watch-later
```

Sanctum issues stateless tokens, so authentication survives horizontal scaling without holding any session state on the server.

## The Interesting Validation Problem

"Title must be unique" is the naive rule. It would reject the 2021 *Dune* because the 1984 one exists. The correct rule is a composite: unique on **(title, release_date)**, and it has to ignore the current record on updates or every edit fails against itself:

```laravel
class MovieRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => [
                'required', 'string', 'max:255',
                Rule::unique('movies')->where(function ($query) {
                    return $query->where('release_date', $this->release_date);
                })->ignore($this->route('movie'))
            ],
            'description' => ['required', 'string', 'min:10'],
            'release_date' => ['required', 'date', 'before_or_equal:today'],
            'genre_id' => ['required', 'exists:genres,id'],
        ];
    }
}
```

The same constraint exists at the database level (**$table->unique(['title', 'release_date'])**), because validation protects users from mistakes and constraints protect data from race conditions. You want both.

## Status Codes Are the API's Body Language

Adding a movie to a watch-later list twice isn't a validation error and isn't a success. It's a **409 Conflict**, a business rule violation the client can handle specifically:

```laravel
public function addToWatchLater(Movie $movie): JsonResponse
{
    $user = Auth::user();

    if ($user->watchLater->contains($movie->id)) {
        return response()->json([
            'message' => 'Movie is already in your watch later list'
        ], 409); // Conflict
    }

    $user->watchLater()->attach($movie);

    return response()->json([
        'message' => 'Movie added to watch later list',
        'movie' => $movie->load('genre'),
    ]);
}
```

Two small details carry weight here: the state check happens *before* the mutation, and the response loads the genre eagerly (**->load('genre')**) so the client doesn't fire another query per movie. That's the classic N+1 shape, killed at the source.

## Tests as the Contract

Every behavior above is pinned by a Pest test: happy path, auth failure, and the duplicate case:

```laravel
test('authenticated user can create a movie', function () {
    $user = User::factory()->create();
    $genre = Genre::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/movies', [
            'title' => 'Inception',
            'description' => 'A mind-bending thriller',
            'release_date' => '2010-07-16',
            'genre_id' => $genre->id,
        ]);

    $response->assertCreated()
        ->assertJson(['title' => 'Inception']);

    $this->assertDatabaseHas('movies', ['title' => 'Inception']);
});

test('cannot add same movie to watch later twice', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();

    $user->watchLater()->attach($movie);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/movies/{$movie->id}/watch-later")
        ->assertStatus(409);
});

test('unauthenticated user cannot create movies', function () {
    $this->postJson('/api/movies', [
        'title' => 'Test',
        'description' => 'Test',
        'release_date' => '2020-01-01',
        'genre_id' => 1,
    ])->assertUnauthorized();
});
```

Tests run against SQLite in memory with factories. Fast enough that there's no excuse to skip them, and readable enough that they double as the API's documentation.

## Performance: Caching the Read Endpoints

Both read endpoints, **GET /api/movies** and **GET /api/movies/{id}**, are served from cache with a one hour TTL, and the genre is loaded eagerly. The reads stay fast and never return stale data, because the cache is invalidated on every **saved** and **deleted** event.

```php
// app/Support/MovieCache.php — single source of truth for keys & TTL
final class MovieCache
{
    public const TTL = 3600;

    public static function allKey(): string { return 'movies.all'; }
    public static function key(int $id): string { return "movie.{$id}"; }

    public static function flush(?int $id = null): void
    {
        Cache::forget(self::allKey());
        if ($id !== null) Cache::forget(self::key($id));
    }
}
```

```php
// app/Models/Movie.php — every write auto-flushes -> no stale reads
protected static function booted(): void
{
    static::saved(fn (Movie $movie) => MovieCache::flush($movie->getKey()));
    static::deleted(fn (Movie $movie) => MovieCache::flush($movie->getKey()));
}
```

```php
// app/Http/Controllers/MovieController.php
$movies = Cache::remember(
    MovieCache::allKey(),
    MovieCache::ttl(),
    fn () => Movie::with('genre')->orderBy('id')->get()
);
```

Why not cache tags? They are tied to a specific driver, namely Redis. Invalidating explicit keys instead (**movies.all**, **movie.{id}**) works with any cache driver, so the database driver is enough for now and Redis is just a config change somewhere down the road.

## Hardening: Rate Limiting

The API rate limits authentication and the read endpoints. The bearer token login is throttled, and an authenticated caller can't hammer **/api/movies**. Laravel's **RateLimiter** facade defines the named limiters once, and they're applied to routes as middleware:

```php
// app/Providers/AppServiceProvider.php
RateLimiter::for('api', fn (Request $request) =>
    Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));

RateLimiter::for('login', fn (Request $request) =>
    Limit::perMinute(5)->by(strtolower($request->input('email')).'|'.$request->ip()));

RateLimiter::for('register', fn (Request $request) =>
    Limit::perMinute(3)->by($request->ip()));
```

```php
// routes/api.php — throttles ride on the same middleware chain
Route::middleware(['throttle:api', 'auth:sanctum'])->group(function () {
    Route::apiResource('movies', MovieController::class);
    // ...
});
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
```

**api** keys by user id (falls back to IP), **login** by email+IP, **register** by IP. The driver is **database**, so no Redis is required. Pest tests pin the contracts, asserting **429** when the limit trips and **422** when credentials arrive in the URL instead of the body.

## Try It Live

The API is deployed, so you can test every endpoint right now in Postman — no local setup needed.

**Base URL** — set one Postman variable to **{{baseUrl}}**:

```
https://libe.dev/demo/movie-api/api/
```

**1. Get a token.** **POST {{baseUrl}}/login** (or **/register**) with a raw JSON body:

```json
{
    "email": "your@email.com",
    "password": "your-password"
}
```
It returns:
```json
{
    "access_token": "1|aB3d...",
    "token_type": "Bearer"
}
```

**2. Auto-store the token** so protected routes "just work" — paste into the Login request's **Tests** tab:
```js
const json = pm.response.json();
pm.environment.set("token", json.access_token);
```
Then set **Authorization → Type: Bearer Token → Token: {{token}}** on every protected request. Every request also needs the header **Accept: application/json** (and **Content-Type: application/json** on POST/PUT).

**3. Exercise the endpoints:**

| Method | URI | Payload / notes | Expect |
|---|---|---|---|
| POST | **{{baseUrl}}/login** | **{"email","password"}** body | **200** + token |
| POST | **{{baseUrl}}/register** | **{"name","email","password","password_confirmation"}** | **200** + token |
| GET | **{{baseUrl}}/movies** | — | **200** list |
| POST | **{{baseUrl}}/movies** | **{"title":"Inception","description":"A mind-bending heist thriller","release_date":"2010-07-16","genre_id":1}** | **201** |
| GET | **{{baseUrl}}/movies/{id}** | — | **200** movie + genre |
| PUT | **{{baseUrl}}/movies/{id}** | same shape as create | **200** |
| DELETE | **{{baseUrl}}/movies/{id}** | — | **200** |
| POST | **{{baseUrl}}/movies/{id}/watch-later** | — | **200**; **409** again |
| DELETE | **{{baseUrl}}/movies/{id}/watch-later** | — | **200** |
| GET | **{{baseUrl}}/watch-later** | — | **200** list |

> **Credentials live in the body, never the URL.** Sending **email**/**password** as query params is rejected with **422** — URLs get logged verbatim by proxies and servers.

**Status codes worth knowing:** **200** ok · **201** created · **401** bad credentials / missing token · **409** duplicate watch-later · **422** validation (bad **genre_id**, short **description**, creds in URL) · **429** rate limited (**login** 5/min per email+IP, **register** 3/min per IP, **movies** 30/min per user).

To run it locally instead (optional), the steps are in the [GitHub README](https://github.com/mrlibelula/movie-api).

If you are early in your backend career, this repo is a reasonable checklist: composite validation rules, correct status codes, eager loading, database constraints mirroring app rules, and tests that cover failure paths. That is what separates an API that works in the demo from one that works in production.

I still care about those fundamentals on larger products, not only in this teaching repo.