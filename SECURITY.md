# Movie API — Security Audit & Hardening Guide

**For Tech Interviewers, Talent Hunters & Future Maintainers**

> This document is a record of a security review of the Movie API (Laravel 11.34.2). It details what was already protected, what was **not**, every vulnerability identified, and the fixes that were implemented — followed by a ready-to-paste prompt that the blog-post agent can use to translate these features into a portfolio article.

---

## 1. Executive Summary

| Aspect | Status |
|--------|--------|
| API endpoint rate limiting | ✅ **Added** (30 req/min per user) |
| API **login** brute-force protection | ✅ **Added** (5 attempts/min per email+IP) |
| API **register** abuse protection | ✅ **Added** (3 attempts/min per IP) |
| Password **reset** throttling | ✅ **Added** (3 attempts/min per IP) |
| Credential-in-URL rejection | ✅ **Added** (login/register reject `email`/`password` in the query string → 422) |
| Token/Sanctum authentication | ✅ Already present |
| Password hashing | ✅ Already present |
| CORS correctness for a public bearer-token API | ✅ **Fixed** |
| Exception / information disclosure | ✅ **Fixed** |
| Security response headers | ✅ **Added** |
| Movie write-access authorization (roles/ownership) | ⚠️ **Known gap** — left as-is by design |
| Email verification on API routes | ⚠️ **Known gap** — skipped (technical but simple) |

---

## 2. What Was Already Protected

Before this audit, the API relied on a few solid Laravel defaults but had **no rate limiting on the API at all**.

### 2.1 Token Authentication (Sanctum)
- Most sensitive routes are guarded by `auth:sanctum` (`routes/api.php`), so a bearer token is required.
- `app/Models/User.php` uses `Laravel\Sanctum\HasApiTokens` and hides `password` / `remember_token` from serialization.
- Passwords are hashed with `Hash::make()` in `AuthController` and re-hashed transparently by the `hashed` cast.

### 2.2 Session Login Brute-Force Limiter (the *only* limiter that existed)
- `app/Http/Requests/Auth/LoginRequest.php` uses a **manual** `RateLimiter` — **5 failed attempts per minute per `email + IP`** before a lockout (`ensureIsNotRateLimited()`, `throttleKey()`).
- **Important caveat found during the audit:** this only covered the **Laravel session/web login** flow wired to `routes/auth.php` → `AuthenticatedSessionController`. The **API bearer-token login** (`routes/api.php` → `AuthController::login`) was a *separate, duplicate* implementation with **zero** brute-force protection.

### 2.3 Email-Verification Throttling
- `routes/auth.php` already applied `throttle:6,1` to email verification routes.

---

## 3. What Was NOT Protected (the vulnerabilities)

### 3.1 No rate limiting on the API endpoints (HIGH)
- Laravel 11 ships **no default `api` limiter** — you must define one. None was defined.
- Consequently `GET /api/movies`, `GET /api/movies/{movie}`, `/api/watch-later`, etc. were **unconditionally scrapable** by any authenticated user (and any remote host) thousands of times per minute.
- **Impact:** scraping, server-load abuse, resource exhaustion.

### 3.2 API login brute-force (HIGH)
- `AuthController::login` (`app/Http/Controllers/AuthController.php`) accepted unlimited login attempts — an attacker could brute-force credentials against the API token endpoint with no lockout.
- This was the direct gap left by the manual limiter described in §2.2 only applying to the session flow.

### 3.3 Unthrottled registration (HIGH)
- `AuthController::register` allowed unlimited account creation → mass account farming / storage abuse.

### 3.4 Unthrottled password reset (MEDIUM)
- `routes/auth.php` `forgot-password` / `reset-password` had **no throttle**, enabling email-bombing of users. (`config/auth.php` had a `throttle => 60` value, but **config alone does nothing** without a limiter wired to it.)

### 3.5 Missing authorization / IDOR (HIGH — intentionally left as a known gap)
- `MovieController::store/update/destroy` perform **no authorization** checks. Any authenticated user can create, edit, or delete **any** movie (no ownership, no roles).
- **Decision:** per project owners, this is left **as-is** for now and documented here so it is a *known, conscious* choice rather than an oversight.

### 3.6 Information disclosure via raw exceptions (MEDIUM)
- `MovieController` returned `$e->getMessage()` to clients in 500 responses (e.g. `store()`, `show()` error branches, `destroy()`). This leaks SQL/driver/stack internals to callers.

### 3.7 Overly permissive CORS (MEDIUM)
- `config/cors.php` had `allowed_methods => ['*']`, `allowed_headers => ['*']`, `paths => ['*']` and `supports_credentials => true`.
- For a **bearer-token** API consumed via Postman / cURL / a browser front-end, cookie credentials aren't used, so `supports_credentials => true` was semantically wrong, and the wildcard method/header lists were looser than needed.
- (Note: CORS only affects **browsers**; Postman and server-to-server clients ignore it entirely.)

### 3.8 No security response headers (LOW)
- No `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, etc.

### 3.9 Code-quality issues (LOW)
- `POST /movies` was registered **three times** (`routes/api.php:14, 19, 28`) — redundant and shadowed.
- `MovieController::update()` validated a non-existent `genre` string column instead of the real `genre_id` → silent no-op on updates.

### 3.10 Credentials accepted in the URL query string (MEDIUM–HIGH)
- `AuthController::login` / `AuthController::register` read inputs with `$request->all()` / `$request->only()`, which merge the **query string and the request body** into a single pool.
- Consequence: credentials placed in the URL (e.g. the "Params" tab in Postman) were silently accepted. URLs are logged verbatim by web servers, reverse proxies, app gateways and stay in browser history — so `?email=...&password=...` is a genuine credential-leak risk.
- **Impact:** credential leakage via logs/history; also encourages a bad client-side habit.

---

## 4. Implemented Solutions

### 4.1 Named rate limiters — `app/Providers/AppServiceProvider.php`
```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute(5)->by(strtolower($request->input('email')).'|'.$request->ip());
});

RateLimiter::for('register', function (Request $request) {
    return Limit::perMinute(3)->by($request->ip());
});

RateLimiter::for('reset', function (Request $request) {
    return Limit::perMinute(3)->by($request->ip());
});
```
- The `api` limiter keys on **user id** (so an authenticated scraper gets a per-account cap) and **falls back to IP** for unauthenticated requests.
- Uses the existing `database` cache driver (`.env` `CACHE_STORE=database`) — no Redis required for persistence.

### 4.2 Routes wired to throttles — `routes/api.php`
```php
Route::middleware(['throttle:api', 'auth:sanctum'])->get('/user', ...);

Route::middleware(['throttle:api', 'auth:sanctum'])->group(function () {
    Route::post('/logout', ...);
    Route::apiResource('movies', MovieController::class);
    // ...watch-later routes
});

Route::post('/register', ...)->middleware('throttle:register');
Route::post('/login', ...)->middleware('throttle:login');
```
- `throttle:login` now gives the **API login the same 5-attempts-per-email+IP protection** that the session login already had — the two flows are finally consistent.
- **Note on ordering:** Laravel reorders middleware by priority, so `Authenticate:sanctum` always runs before `ThrottleRequests:api`. Unauthenticated hiters of protected routes receive `401`, and authenticated users are capped at 30 req/min — both layers work regardless of the list order.

### 4.3 Password-reset throttling — `routes/auth.php`
```php
->middleware(['guest', 'throttle:reset'])   // forgot-password
->middleware(['guest', 'throttle:reset'])   // reset-password
```

### 4.4 Removed the redundant `POST /movies` registration
- `routes/api.php` now registers movie routes **once** via `Route::apiResource('movies', ...)`.

### 4.5 Fixed `update()` validation — `app/Http/Controllers/MovieController.php`
- Changed the bogus `'genre' => 'string|max:100'` rule to the correct nullable `genre_id` foreign key rule:
  ```php
  'genre_id' => 'nullable|exists:genres,id',
  ```

### 4.6 Stopped leaking exceptions — `app/Http/Controllers/MovieController.php`
- Replaced `'error' => $e->getMessage()` in 500 responses with generic messages; details remain in `Log::error(...)` server-side only.

### 4.7 CORS correctness — `config/cors.php`
```php
'paths'              => ['api/*', 'sanctum/csrf-cookie'],
'allowed_methods'    => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
'allowed_origins'    => ['*'],   // open to any browser origin
'allowed_headers'    => ['Content-Type', 'X-Requested-With', 'Authorization', 'Accept'],
'supports_credentials' => false, // bearer tokens, not cookies
```
- Any browser front-end can call the API, Postman/cURL are unaffected (they ignore CORS), and `supports_credentials => false` is the technically correct setting for a bearer-token API.

### 4.8 Security headers — `app/Http/Middleware/SecurityHeaders.php`
```php
$response->headers->set('X-Content-Type-Options', 'nosniff');
$response->headers->set('X-Frame-Options', 'SAMEORIGIN');
$response->headers->set('Referrer-Policy', 'no-referrer');
$response->headers->set('X-XSS-Protection', '0');
$response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
```
- Registered globally in `bootstrap/app.php` via `$middleware->append(...)`.

### 4.9 Reject credentials in the URL — `app/Http/Controllers/AuthController.php`
- A private guard runs **before validation** in both `login()` and `register()`. It inspects the query parameter bag (`$request->query->has(...)`) for credential fields and returns `422` if any are present:
  ```php
  private function rejectQueryCredentials(Request $request, array $fields)
  {
      $found = collect($fields)->filter(fn ($field) => $request->query->has($field));

      if ($found->isNotEmpty()) {
          return response()->json([
              'message' => 'Credentials must be sent in the request body, not the URL query string.',
              'errors' => $found->mapWithKeys(fn ($field) => [$field => ['Use the request body instead of the URL for this field.']]),
          ], 422);
      }

      return null;
  }
  ```
- Called with `['email', 'password']` from `login()` and `['name', 'email', 'password', 'password_confirmation']` from `register()`.
- Because the guard rejects query-string credentials, the remaining `$request->all()` / `$request->only()` reads can only ever contain **body** input (JSON or `application/x-www-form-urlencoded`). The URL is no longer an accepted channel for secrets.

---

## 5. Endpoint-by-Endpoint Analysis

| Endpoint | Auth | Throttle | Notes |
|---|---|---|---|
| `POST /api/register` | public | 3/min/IP | Added; body-only credentials (422 if in URL) |
| `POST /api/login` | public | 5/min/email+IP | Added (was unprotected); body-only credentials (422 if in URL) |
| `POST /api/logout` | token | 30/min/user | Added |
| `GET/POST/PUT/DELETE /api/movies*` | token | 30/min/user | Added |
| `POST/DELETE /api/movies/{id}/watch-later` | token | 30/min/user | Added |
| `GET /api/watch-later` | token | 30/min/user | Added |
| `GET /api/user` | token | 30/min/user | Added |
| `POST /forgot-password`, `/reset-password` | guest | 3/min/IP (`throttle:reset`) | Added |
| `GET /verify-email/*` | auth+signed | 6/min (`throttle:6,1`) | Pre-existing |

---

## 6. Known Remaining Gaps (Conscious Decisions)

1. **Movie write authorization / IDOR** — no roles or ownership on `store/update/destroy`. *Deliberately deferred.*
2. **Email verification on API routes** — `EnsureEmailIsVerified` middleware exists but is not applied to API routes. *Deliberately skipped to keep it technical but simple.*
3. **Password policy** — currently `min:8` only (no complexity or leaked-password validation).
4. **Token expiry** — bearer tokens currently have no explicit TTL in `config/sanctum.php`.
5. **HTTP Strict Transport Security (HSTS)** — not set in the SecurityHeaders middleware (deploy-time consideration).
6. **`X-XSS-Protection: 0`** — set to `0` by design (modern browsers use CSP; the legacy header can introduce filtering bugs).

---

## 7. Verification

New tests live in `tests/Feature/SecurityRateLimitTest.php`:
- Authenticated user limited to **30 req/min** (429 on the 31st).
- Unauthenticated protected-route rejection (401).
- API login brute-force lockout (429 after 5 fails).
- Register throttling per IP (429 after 3).
- Security headers present.

`tests/Feature/Auth/ApiCredentialsTest.php` proves the credential-in-URL guard:
- Login with credentials in the URL query → **422**, no token issued.
- Login with credentials in a **JSON** body → **200** + token (regression).
- Login with credentials in an **x-www-form-urlencoded** body → **200** + token (regression).
- Register with credentials in the URL query → **422**.

Full suite: `vendor/bin/pest` → **39 passed, 209 assertions**.

Style: `vendor/bin/pint --test` → clean (63 files; only PHP 8.4 deprecation notices from Pint's bundled deps, none from project code).

---

## 8. Blog-Post Agent Prompt

> Copy the block below and paste it verbatim to the agent responsible for writing the portfolio blog post. It turns this security work into a recruiter-facing article.

```text
You are writing a technical blog post for a developer's portfolio site about a Laravel 11
REST API project ("Movie API"). The post is titled something like:
"How I Hardened My Laravel API: Rate Limiting, Brute-Force Protection & Beyond."

Write in a clear, first-person developer voice aimed at tech interviewers and other
engineers. Structure it as follows and INCLUDE real code snippets:

1. INTRO - Why security matters even for a small/portfolio API. Note that "it's just a
   demo" is not an excuse to ship an open, scrapeable endpoint.

2. THE PROBLEM - Describe the two-login-flow bug: the Laravel session login had a manual
   5-attempts-per-email+IP RateLimiter (LoginRequest), but the SEPARATE API bearer-token
   login (AuthController::login) had ZERO brute-force protection. Also explain that the
   API had no rate limiting at all - any authenticated user could scrape /api/movies
   thousands of times.

3. THE FIX - Explain Laravel's RateLimiter facade and (RateLimiter::for) named limiters
   defined in AppServiceProvider: an 'api' limiter (30 req/min keyed by user id, falling
   back to IP), 'login' (5/min per email+IP), 'register' (3/min per IP), and 'reset'
   (3/min per IP). Show how throttles are applied to routes via 'throttle:api' etc.
   Mention that the cache driver (database) needs no Redis.

4. COOKIE VS TOKEN AUTH (CORS) - Explain that CORS only affects browsers; Postman/cURL
   ignore it. For a bearer-token API you don't need supports_credentials - show the
   hardened config/cors.php (allowed_origins => ['*'], supports_credentials => false).

5. LAYERED DEFENSE - Cover the other hardening: stopping raw exception leakage in
   MovieController (turn $e->getMessage() into generic messages + Log::error), adding
   security response headers via a SecurityHeaders middleware, and fixing a validation
   bug (bogus 'genre' string renamed to the correct 'genre_id' foreign key).

6. NEVER PUT SECRETS IN THE URL - Explain WHY query-string credentials are dangerous:
   URLs get logged verbatim by browsers, proxies, and servers. Show that AuthController
   originally read via $request->only()/$request->all() (which also swallowed query
   params), so a Postman "Params" tab worked but leaked the password. Show the fix: a
   rejectQueryCredentials() guard returning 422, and note that credentials must now be
   sent in the request body (JSON or x-www-form-urlencoded) - never the URL.

7. TESTING - Show the Pest tests that prove the throttles work (asserting 429), plus the
   ApiCredentialsTest proving URL credentials are rejected (422) while JSON and
   form-encoded bodies still return a token. State that the full suite passes (39 tests /
   209 assertions).

8. HONESTY SECTION - List the remaining known gaps you consciously deferred (movie
   write-authorization/IDOR, email verification on API routes, password policy, token
   expiry). This honesty strengthens the post.

9. CONCLUSION - Practical takeaways for other devs.

Tone: genuine, technical, no overclaiming. Use real file paths. Do NOT invent security
features that are not in the codebase. Keep the post skimmable with headings and code
blocks.
```

---

*This document reflects the state of the repository after the hardening pass. Run `vendor/bin/pest` to confirm the suite remains green.*
