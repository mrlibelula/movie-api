# Movie API — Technical Overview

## Stack and Runtime
- Runtime: PHP 8.2+, Laravel 11.9 skeleton (`composer.json`), SQLite by default.
- Auth: Laravel Sanctum for personal access tokens.
- Language/Framework tooling: Composer-managed PHP app; no Node/JS runtime required (empty `package-lock.json`).
- HTTP style: JSON-first REST API, stateless auth with bearer tokens.

## Key Dependencies
- Prod: `laravel/framework`, `laravel/sanctum`, `laravel/tinker`.
- Dev/Tooling: Pest (`pestphp/pest`, `pest-plugin-laravel`), Laravel Breeze scaffolding, Pint for lint/format, Laravel Sail (optional local Docker), Faker, Mockery, Collision.
- Composer scripts auto-copy `.env`, generate app key, create SQLite file, and run migrations on project creation.

## Architecture
- Controllers
  - `AuthController` handles register/login/logout with validation and Sanctum token issuance.
  - `MovieController` exposes CRUD plus watch-later operations; includes validation, duplicate-check handling, and friendly error JSON.
- Middleware
  - `EnsureValidToken` blocks requests without a bearer token before Sanctum auth.
  - Core group uses `auth:sanctum` for all protected routes.
- Models/Relations
  - `User` uses `HasApiTokens`; `watchLater()` many-to-many with `Movie` via `movie_user`.
  - `Movie` belongs to `Genre`; hidden pivot data; unique compound index on `title` + `release_date`.
  - `Genre` has many `Movie`.

## Routing and API Surface
- Public: `POST /api/register`, `POST /api/login`.
- Authenticated (`auth:sanctum`, some additionally check `EnsureValidToken`):
  - `GET /api/user` returns current user.
  - Movies: `GET /api/movies`, `GET /api/movies/{id}`, `POST /api/movies`, `PUT/PATCH /api/movies/{id}`, `DELETE /api/movies/{id}`.
  - Watch later: `POST /api/movies/{movie}/watch-later`, `DELETE /api/movies/{movie}/watch-later`, `GET /api/watch-later`.
  - `POST /api/logout` revokes current token.
- Note: `POST /api/movies` is registered twice (once inside the `EnsureValidToken` group and once in the main `auth:sanctum` group); behavior is identical but duplication exists.

## API Usage Guide
- Base URL (local): `http://localhost:8000/api`; Demo: `https://libe.dev/demo/movie-api/v1/api`.
- Auth header for protected calls: `Authorization: Bearer <token>`.
- Typical flow:
  1) Register `POST /register` with `{ "name", "email", "password", "password_confirmation" }`.
  2) Login `POST /login` with `{ "email", "password" }` → returns `access_token`.
  3) Call protected endpoints with the bearer token.
- Movie payload example:
  - Create `POST /movies` body: `{ "title": "Inception", "description": "…", "release_date": "2010-07-16", "genre_id": 1 }`.
  - Update `PUT /movies/{id}` body: any subset of `title`, `description`, `release_date`, `genre_id`.
- Watch later:
  - Add `POST /movies/{movie}/watch-later`
  - Remove `DELETE /movies/{movie}/watch-later`
  - List `GET /watch-later`
- Error/status patterns: 401 (no/invalid token), 404 (missing resource), 409 (already in watch-later), 422 (validation), 500 (server).

## Data Model (Migrations)
- `users`: id, name, unique email, password, email_verified_at, remember_token, timestamps.
- `movies`: id, title, description, release_date (date), `genre_id` FK, unique constraint on (title, release_date), timestamps.
- `genres`: id, unique name, timestamps.
- `movie_user`: pivot for watch-later list; unique (user_id, movie_id); cascades on delete.
- `watch_later`: legacy/unused table also linking user_id/movie_id (migration exists but relationships use `movie_user`).

## Validation and Errors
- Auth: required name/email/password with confirmation; login validates email/password; returns 422 on validation, 401 on bad credentials.
- Movies: create requires title/description/release_date/genre_id; duplicate title+date returns 422; show/delete return 404 when missing; watch-later operations return 401/404/409 as applicable.

## Testing and Quality
- Tests: Pest-powered feature tests under `tests/Feature` (Auth, movie CRUD, watch-later flows).
- Commands: `php artisan test` for suite; `./vendor/bin/pint` for styling.

## Caching
- **Data-layer caching** added for the read endpoints: `GET /api/movies` (key `movies.all`) and `GET /api/movies/{id}` (key `movie.{id}`), both at a 1-hour TTL.
- **Driver:** `database` (`CACHE_STORE=database`) — driver-agnostic, config-only upgrade to `redis` in production.
- **Invalidation:** centralized in the `Movie` model via Eloquent `saved`/`deleted` events (see `booted()`), which call `MovieCache::flush()` on every create/update/delete — guaranteeing no stale reads.
- **Key/TTL helper:** `app/Support/MovieCache.php`.
- **Tests:** `tests/Feature/MovieCacheTest.php` (Pest) covers cache population and invalidation.
- See **`CACHE.md`** for full details, trade-offs, and future work.

## Local Setup (summary)
1. `composer install`
2. Copy `.env.example` to `.env`; set `DB_CONNECTION=sqlite` and point `DB_DATABASE` to `database/database.sqlite`.
3. Create SQLite file (`touch database/database.sqlite`), run `php artisan key:generate`, then `php artisan migrate --seed` (optional seed).
4. Serve locally with `php artisan serve` (default `http://localhost:8000`).

## Operational Notes
- Auth tokens: Sanctum personal access tokens issued on register/login; send as `Authorization: Bearer {token}`.
- Error format: JSON with `message` and `errors`/`data` keys; client-ready status codes (401/404/409/422/500).
- Live demo base URL (per README): `https://libe.dev/demo/movie-api/v1/api`.

