# Movie API - Complete Technical Documentation
**For Tech Interviewers & Talent Hunters**

---

## Project Overview

**Project Name:** Movie API  
**Type:** RESTful API Backend  
**Purpose:** A production-ready Laravel-based REST API for managing movies and user watch lists with authentication  
**Live Demo:** https://libe.dev/demo/movie-api/v1/api  
**GitHub:** https://github.com/mrlibelula/movie-api

This is a backend-only API application built to demonstrate modern PHP development practices, RESTful API design, authentication implementation, and testing strategies suitable for production environments.

---

## Technology Stack

### Core Framework & Runtime
- **PHP Version:** 8.2+ (leveraging modern PHP features)
- **Framework:** Laravel 11.9 (latest major version)
- **Architecture Pattern:** MVC (Model-View-Controller) with API-first design
- **Database:** SQLite 3 (lightweight, portable, perfect for demos and testing)
- **API Style:** JSON-first RESTful API with stateless authentication

### Why This Stack?
- **Laravel 11.x:** Latest stable release with improved performance, cleaner syntax, and modern PHP 8.2+ features
- **PHP 8.2+:** Type declarations, enums, readonly properties, and performance improvements
- **SQLite:** Zero-configuration database, perfect for rapid development and deployment
- **Sanctum:** Official Laravel solution for SPA and mobile API authentication

---

## Complete Package Dependencies

### Production Dependencies (composer.json)

| Package | Version | Purpose |
|---------|---------|---------|
| `laravel/framework` | ^11.9 | Core framework providing routing, ORM, validation, etc. |
| `laravel/sanctum` | ^4.0 | API token authentication system for SPAs and mobile apps |
| `laravel/tinker` | ^2.9 | Powerful REPL for interacting with the application |

### Development Dependencies (composer.json)

| Package | Version | Purpose |
|---------|---------|---------|
| `pestphp/pest` | ^2.0 | Modern testing framework - elegant, expressive syntax |
| `pestphp/pest-plugin-laravel` | ^2.0 | Laravel-specific Pest helpers and assertions |
| `laravel/breeze` | ^2.1 | Minimal authentication scaffolding (used for initial setup) |
| `laravel/pint` | ^1.13 | Opinionated PHP code style fixer (Laravel's coding standards) |
| `laravel/sail` | ^1.26 | Docker development environment (optional) |
| `fakerphp/faker` | ^1.23 | Generate fake data for testing and seeding |
| `mockery/mockery` | ^1.6 | Mocking framework for unit tests |
| `nunomaduro/collision` | ^8.0 | Beautiful error reporting for console and testing |

### Frontend/JavaScript
- **No Node.js dependencies** - This is a pure backend API
- Empty `package-lock.json` confirms no JavaScript runtime required
- Frontend-agnostic: can be consumed by React, Vue, Angular, mobile apps, etc.

---

## Application Architecture

### Directory Structure
```
app/
├── Exceptions/
│   └── Handler.php              # Global exception handling
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php   # Registration, login, logout
│   │   ├── MovieController.php  # CRUD + watch-later operations
│   │   └── Controller.php       # Base controller
│   ├── Middleware/
│   │   ├── EnsureValidToken.php # Custom token validation
│   │   └── EnsureEmailIsVerified.php
│   └── Requests/
│       └── Auth/
│           └── LoginRequest.php # Form request validation
├── Models/
│   ├── User.php                 # User model with Sanctum
│   ├── Movie.php                # Movie model with relationships
│   └── Genre.php                # Genre model
└── Providers/
    └── AppServiceProvider.php   # Service container bindings
```

### Design Patterns & Best Practices

#### 1. **RESTful Resource Design**
- Standard HTTP verbs (GET, POST, PUT, PATCH, DELETE)
- Resource-oriented URLs (`/api/movies`, `/api/movies/{id}`)
- Proper HTTP status codes (200, 201, 401, 404, 409, 422, 500)

#### 2. **Eloquent ORM**
- Active Record pattern for database interaction
- Relationship definitions (belongsTo, belongsToMany)
- Mass assignment protection with `$fillable`
- Attribute casting and hiding sensitive data

#### 3. **Middleware for Cross-Cutting Concerns**
- Authentication via `auth:sanctum`
- Custom `EnsureValidToken` middleware for additional validation
- Middleware groups for route protection

#### 4. **Request Validation**
- Form Request classes for complex validation logic
- Inline validation for simpler cases
- Automatic 422 responses with validation errors

#### 5. **Factory Pattern for Testing**
- Model factories for generating test data
- Faker integration for realistic test data
- Seeders for initial database population

---

## Database Schema

### Tables & Relationships

#### **users**
```php
id                 : bigint (primary key)
name              : string
email             : string (unique)
email_verified_at : timestamp (nullable)
password          : string (hashed)
remember_token    : string (nullable)
created_at        : timestamp
updated_at        : timestamp
```

#### **movies**
```php
id            : bigint (primary key)
title         : string
description   : text
release_date  : date
genre_id      : bigint (foreign key -> genres.id)
created_at    : timestamp
updated_at    : timestamp

UNIQUE INDEX: (title, release_date)  // Prevents duplicate movies
```

#### **genres**
```php
id         : bigint (primary key)
name       : string (unique)
created_at : timestamp
updated_at : timestamp
```

#### **movie_user** (Pivot Table)
```php
id         : bigint (primary key)
user_id    : bigint (foreign key -> users.id, cascade on delete)
movie_id   : bigint (foreign key -> movies.id, cascade on delete)
created_at : timestamp
updated_at : timestamp

UNIQUE INDEX: (user_id, movie_id)  // User can't add same movie twice
```

#### **personal_access_tokens** (Sanctum)
```php
id             : bigint (primary key)
tokenable_type : string
tokenable_id   : bigint
name           : string
token          : string (unique, hashed)
abilities      : text (nullable)
last_used_at   : timestamp (nullable)
expires_at     : timestamp (nullable)
created_at     : timestamp
updated_at     : timestamp
```

### Eloquent Relationships

```php
// User Model
User hasMany PersonalAccessToken (via Sanctum's HasApiTokens)
User belongsToMany Movie (through 'movie_user' pivot)

// Movie Model
Movie belongsTo Genre
Movie belongsToMany User (through 'movie_user' pivot)

// Genre Model
Genre hasMany Movie
```

---

## Authentication & Authorization

### Authentication System: Laravel Sanctum

**Why Sanctum?**
- Designed specifically for SPAs and mobile applications
- Lightweight token-based authentication
- No OAuth complexity when not needed
- Official Laravel package with first-party support

**Authentication Flow:**

1. **Registration** (`POST /api/register`)
   ```json
   Request: {
     "name": "John Doe",
     "email": "john@example.com",
     "password": "password123",
     "password_confirmation": "password123"
   }
   
   Response: {
     "user": { "id": 1, "name": "John Doe", "email": "john@example.com" },
     "access_token": "1|ABC123..."
   }
   ```

2. **Login** (`POST /api/login`)
   ```json
   Request: {
     "email": "john@example.com",
     "password": "password123"
   }
   
   Response: {
     "user": { "id": 1, "name": "John Doe", "email": "john@example.com" },
     "access_token": "2|XYZ789..."
   }
   ```

3. **Authenticated Requests**
   ```
   Authorization: Bearer 2|XYZ789...
   ```

4. **Logout** (`POST /api/logout`)
   - Revokes current token
   - User must login again to get new token

### Middleware Stack

1. **`auth:sanctum`** - Core Sanctum authentication
   - Validates bearer token
   - Loads authenticated user
   - Returns 401 if token invalid

2. **`EnsureValidToken`** - Custom middleware
   - Additional validation layer
   - Applied to sensitive operations (movie creation)
   - Can implement custom business logic

---

## API Endpoints

### Public Endpoints (No Authentication)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/register` | Create new user account |
| POST | `/api/login` | Authenticate and receive token |

### Protected Endpoints (Require Bearer Token)

#### User Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/user` | Get current authenticated user |
| POST | `/api/logout` | Revoke current token |

#### Movie CRUD
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/movies` | List all movies |
| POST | `/api/movies` | Create new movie |
| GET | `/api/movies/{id}` | Get specific movie |
| PUT/PATCH | `/api/movies/{id}` | Update movie |
| DELETE | `/api/movies/{id}` | Delete movie |

#### Watch Later Feature
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/movies/{movie}/watch-later` | Add movie to watch later |
| DELETE | `/api/movies/{movie}/watch-later` | Remove from watch later |
| GET | `/api/watch-later` | Get user's watch later list |

### Request/Response Examples

#### Create Movie
```http
POST /api/movies
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Inception",
  "description": "A thief who steals corporate secrets...",
  "release_date": "2010-07-16",
  "genre_id": 1
}

Response (201 Created):
{
  "id": 1,
  "title": "Inception",
  "description": "A thief who steals corporate secrets...",
  "release_date": "2010-07-16",
  "genre_id": 1,
  "created_at": "2024-09-04T10:30:00.000000Z",
  "updated_at": "2024-09-04T10:30:00.000000Z",
  "genre": {
    "id": 1,
    "name": "Science Fiction"
  }
}
```

#### Error Responses
```json
// 401 Unauthorized
{
  "message": "Unauthenticated."
}

// 404 Not Found
{
  "message": "Movie not found"
}

// 409 Conflict (movie already in watch later)
{
  "message": "Movie is already in your watch later list"
}

// 422 Validation Error
{
  "message": "The given data was invalid.",
  "errors": {
    "title": ["The title field is required."],
    "release_date": ["The release date must be a valid date."]
  }
}
```

---

## Testing Strategy

### Testing Framework: Pest PHP

**Why Pest?**
- Modern, elegant syntax over PHPUnit
- Better test readability
- Powerful expectations API
- Laravel-specific helpers via plugin
- Growing adoption in Laravel community

### Test Structure

```
tests/
├── Feature/
│   ├── Auth/
│   │   ├── AuthenticationTest.php      # Login/logout flows
│   │   ├── RegistrationTest.php        # User registration
│   │   ├── PasswordResetTest.php       # Password reset flow
│   │   └── EmailVerificationTest.php   # Email verification
│   ├── MovieCrudTest.php                # Movie CRUD operations
│   ├── MovieTest.php                    # Additional movie tests
│   └── WatchLaterTest.php               # Watch later functionality
├── Pest.php                             # Pest configuration
└── TestCase.php                         # Base test case
```

### Testing Best Practices Implemented

1. **In-Memory Database**
   - Tests use SQLite `:memory:` database
   - No real database pollution
   - Fast test execution

2. **Fake Storage**
   - `Storage::fake()` for file operations
   - No actual file system writes during tests

3. **Database Transactions**
   - Each test runs in a transaction
   - Automatic rollback after test
   - Clean state for every test

4. **Factory-Based Test Data**
   - Model factories generate consistent test data
   - Realistic data via Faker
   - Reduces test code duplication

### Example Pest Test

```php
test('authenticated user can create a movie', function () {
    $user = User::factory()->create();
    $genre = Genre::factory()->create();
    
    $response = $this->actingAs($user)
        ->postJson('/api/movies', [
            'title' => 'Test Movie',
            'description' => 'Test Description',
            'release_date' => '2024-01-01',
            'genre_id' => $genre->id,
        ]);
    
    $response->assertStatus(201)
        ->assertJson([
            'title' => 'Test Movie',
        ]);
        
    $this->assertDatabaseHas('movies', [
        'title' => 'Test Movie',
    ]);
});
```

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/MovieCrudTest.php

# Run with coverage
php artisan test --coverage
```

---

## Code Quality & Standards

### Laravel Pint (Code Formatter)

- **Purpose:** Enforces Laravel coding standards
- **Based on:** PHP-CS-Fixer with Laravel preset
- **Features:**
  - PSR-12 coding style
  - Laravel-specific conventions
  - Automatic code formatting
  - Zero configuration needed

```bash
# Check code style
./vendor/bin/pint --test

# Fix code style
./vendor/bin/pint
```

### Validation & Business Rules

1. **Unique Movie Constraint**
   - Same title + release_date combination not allowed
   - Database-level unique index
   - Prevents data duplication

2. **Watch Later Logic**
   - User can't add same movie twice (409 Conflict)
   - Deleting movie removes from all watch later lists (cascade)
   - Deleting user removes all their watch later entries (cascade)

3. **Request Validation**
   - Email format validation
   - Password confirmation matching
   - Required field validation
   - Date format validation
   - Foreign key existence validation

---

## Development Setup

### Prerequisites
- PHP 8.2 or higher
- Composer 2.x
- SQLite 3

### Installation Steps

```bash
# 1. Clone repository
git clone https://github.com/mrlibelula/movie-api.git
cd movie-api

# 2. Install dependencies
composer install

# 3. Environment configuration
cp .env.example .env

# 4. Update .env for SQLite
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite

# 5. Create SQLite database
touch database/database.sqlite

# 6. Generate application key
php artisan key:generate

# 7. Run migrations
php artisan migrate

# 8. (Optional) Seed database
php artisan db:seed

# 9. Start development server
php artisan serve
```

### Available Artisan Commands

```bash
# Database
php artisan migrate        # Run migrations
php artisan migrate:fresh  # Drop all tables and re-migrate
php artisan db:seed        # Run seeders

# Development
php artisan serve          # Start development server
php artisan tinker         # Interactive REPL
php artisan route:list     # List all routes

# Testing & Quality
php artisan test           # Run Pest tests
./vendor/bin/pint          # Format code

# Cache & Optimization
php artisan config:cache   # Cache configuration
php artisan route:cache    # Cache routes
php artisan optimize       # Optimize for production
```

---

## Production Deployment

### Environment Configuration

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Use MySQL/PostgreSQL in production
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=movie_api
DB_USERNAME=db_user
DB_PASSWORD=secure_password

# Session & Cache
SESSION_DRIVER=redis
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis

# Security
SANCTUM_STATEFUL_DOMAINS=your-frontend-domain.com
SESSION_DOMAIN=.your-domain.com
```

### Production Checklist

- [ ] Set `APP_DEBUG=false`
- [ ] Use MySQL/PostgreSQL instead of SQLite
- [ ] Configure Redis for caching and queues
- [ ] Set up HTTPS/SSL certificates
- [ ] Configure CORS for frontend domain
- [ ] Run `php artisan optimize`
- [ ] Set up proper logging and monitoring
- [ ] Configure automated backups
- [ ] Set up queue workers (`php artisan queue:work`)
- [ ] Configure proper file permissions
- [ ] Use environment variables for secrets

### Server Requirements

- PHP 8.2+ with extensions: OpenSSL, PDO, Mbstring, Tokenizer, XML, Ctype, JSON
- Web Server: Nginx or Apache with mod_rewrite
- Database: MySQL 5.7+, PostgreSQL 10+, or SQLite 3.8.8+
- Composer
- Redis (optional, for caching and queues)

---

## Key Technical Decisions & Rationale

### 1. **Why Laravel?**
- **Mature Ecosystem:** Battle-tested framework with 10+ years of development
- **Eloquent ORM:** Intuitive, powerful database abstraction
- **Built-in Authentication:** Sanctum provides official API authentication
- **Developer Experience:** Excellent documentation, large community
- **Modern PHP:** Embraces PHP 8+ features

### 2. **Why Sanctum Over Passport?**
- **Lighter Weight:** No OAuth2 complexity when not needed
- **SPA-Focused:** Perfect for single-page and mobile apps
- **First-Party:** Official Laravel package with guaranteed support
- **Simple Tokens:** Easy to understand and debug
- **Performance:** Less overhead than full OAuth2

### 3. **Why SQLite for This Project?**
- **Zero Configuration:** No database server setup required
- **Portability:** Single file database, easy to share
- **Perfect for Demos:** Ideal for portfolio projects
- **Testing:** Excellent for in-memory test databases
- **Production Ready:** Can scale for small-medium applications

### 4. **Why Pest Over PHPUnit?**
- **Readability:** Tests read like specifications
- **Less Boilerplate:** Cleaner, more concise syntax
- **Better DX:** Superior developer experience
- **Modern Approach:** Gaining rapid adoption in Laravel community
- **Backward Compatible:** Built on PHPUnit, can mix both

### 5. **Why No Frontend?**
- **Separation of Concerns:** API-first allows any frontend technology
- **Flexibility:** Can serve React, Vue, Angular, mobile apps
- **Microservices Ready:** Easy to integrate into larger systems
- **Clear Boundaries:** Backend developers focus on API contracts

---

## API Design Principles

### 1. **RESTful Resource Naming**
- Plural nouns for collections (`/movies`, not `/movie`)
- Consistent URL structure
- Resource nesting for relationships (`/movies/{id}/watch-later`)

### 2. **HTTP Status Codes**
- `200 OK` - Successful GET, PUT, PATCH
- `201 Created` - Successful POST
- `204 No Content` - Successful DELETE
- `401 Unauthorized` - Authentication required
- `404 Not Found` - Resource doesn't exist
- `409 Conflict` - Business rule violation (duplicate)
- `422 Unprocessable Entity` - Validation errors
- `500 Internal Server Error` - Server-side errors

### 3. **JSON Response Structure**
```json
// Success responses - return data directly
{
  "id": 1,
  "title": "Movie Title",
  "...": "..."
}

// Error responses - consistent structure
{
  "message": "Human-readable error message",
  "errors": {
    "field_name": ["Error detail"]
  }
}
```

### 4. **Versioning Consideration**
- Current: No explicit versioning (v1 implicit)
- Future: Can add `/api/v2` for breaking changes
- Backward Compatibility: Maintain old endpoints when possible

---

## Security Considerations

### 1. **Authentication**
- Token-based authentication (Sanctum)
- Tokens stored hashed in database
- No password storage in plain text (bcrypt hashing)

### 2. **Authorization**
- Middleware protects all sensitive endpoints
- Users can only modify their own watch later list
- Token required for all protected operations

### 3. **Input Validation**
- All inputs validated before processing
- Laravel's validation rules prevent injection
- Mass assignment protection via `$fillable`

### 4. **SQL Injection Prevention**
- Eloquent ORM uses parameter binding
- Query builder prevents SQL injection
- No raw queries without parameter binding

### 5. **CORS Configuration**
- Configurable for specific frontend domains
- Prevents unauthorized cross-origin requests

### 6. **Rate Limiting**
- Laravel's built-in rate limiting available
- Can limit API calls per user/IP
- Prevents abuse and DDoS attacks

---

## Performance Optimization

### 1. **Database**
- Indexes on frequently queried columns (email, title+release_date)
- Foreign key constraints with proper indexing
- N+1 query prevention with eager loading (`with()`)

### 2. **Caching**
- **Data-layer caching:** `GET /api/movies` and `GET /api/movies/{id}` are served from the cache using the Laravel `Cache` facade (database driver, 1-hour TTL). See `CACHE.md`.
- **Automatic invalidation:** the `Movie` model flushes the relevant keys via Eloquent `saved`/`deleted` events, so writes never leave stale reads.
- Route caching in production (`php artisan route:cache`)
- Config caching (`php artisan config:cache`)
- View caching for any Blade templates

### 3. **Query Optimization**
- Eager loading relationships to prevent N+1
- Select only needed columns
- Pagination for large datasets (easily added)

### 4. **API Response**
- Hide unnecessary data (`$hidden` in models)
- JSON serialization optimization
- Minimal response payload

---

## Scalability Considerations

### 1. **Horizontal Scaling**
- Stateless authentication (tokens) enables load balancing
- No server-side sessions required
- Can deploy across multiple servers

### 2. **Database Scaling**
- Easy migration from SQLite to MySQL/PostgreSQL
- Can implement read replicas
- Database connection pooling support

### 3. **Caching Layer**
- Movie read endpoints (`GET /movies`, `GET /movies/{id}`) are cached in the application, with automatic invalidation on writes (`CACHE.md`).
- Redis is a config-only upgrade (`CACHE_STORE=redis`) and can also back sessions and queues.

### 4. **API Rate Limiting**
- Protect against abuse
- Ensure fair resource distribution
- Laravel middleware for rate limiting

---

## Learning Outcomes & Skills Demonstrated

### Backend Development
✓ RESTful API design and implementation  
✓ Token-based authentication systems  
✓ Database design and relationships  
✓ ORM (Eloquent) mastery  
✓ Middleware and request lifecycle  
✓ Input validation and error handling  

### Software Engineering
✓ MVC architecture pattern  
✓ Separation of concerns  
✓ Dependency injection  
✓ Factory pattern for testing  
✓ Repository pattern (via Eloquent)  

### Testing & Quality
✓ Feature testing with Pest  
✓ Test-driven development practices  
✓ In-memory testing strategy  
✓ Code style enforcement  
✓ Continuous integration ready  

### DevOps & Deployment
✓ Environment configuration management  
✓ Database migrations and seeding  
✓ Production deployment considerations  
✓ Performance optimization  

### Modern PHP
✓ PHP 8.2+ features  
✓ Type declarations  
✓ Namespaces and autoloading  
✓ Composer dependency management  

---

## Future Enhancement Possibilities

### Features
- [ ] Pagination for movie listings
- [ ] Advanced search and filtering
- [ ] Movie ratings and reviews
- [ ] User profiles with avatars
- [ ] Social features (follow users)
- [ ] Recommendations engine
- [ ] Email notifications
- [ ] Movie poster/image uploads

### Technical Improvements
- [ ] API versioning (v2)
- [ ] GraphQL endpoint option
- [ ] Real-time notifications (WebSockets)
- [ ] Full-text search (Elasticsearch)
- [ ] Background job processing
- [ ] API documentation (OpenAPI/Swagger)
- [ ] Performance monitoring (New Relic, DataDog)
- [ ] CI/CD pipeline (GitHub Actions)

### Infrastructure
- [ ] Docker containerization
- [ ] Kubernetes deployment
- [ ] CDN for static assets
- [ ] Multi-region deployment
- [ ] Automated backups
- [ ] Monitoring and alerting

---

## Conclusion

This Movie API project demonstrates a production-ready approach to building modern REST APIs with Laravel. It showcases:

- **Modern PHP Development:** Leveraging PHP 8.2+ and Laravel 11's latest features
- **Security Best Practices:** Token-based authentication, input validation, SQL injection prevention
- **Testing Excellence:** Comprehensive test coverage with modern Pest framework
- **Clean Architecture:** Proper separation of concerns, middleware, and request validation
- **API Design:** RESTful principles, proper HTTP status codes, and JSON responses
- **Code Quality:** Laravel Pint for formatting, PSR-12 standards, readable codebase
- **Production Ready:** Deployment considerations, scaling strategies, performance optimization

The project is an excellent demonstration of skills relevant to backend developer positions, particularly for roles involving:
- Laravel/PHP backend development
- RESTful API design and implementation
- Database design and optimization
- Authentication and authorization systems
- Test-driven development
- Modern software engineering practices

---

## Contact & Links

**Live API Demo:** https://libe.dev/demo/movie-api/v1/api  
**GitHub Repository:** https://github.com/mrlibelula/movie-api  
**Developer Portfolio:** https://libe.dev  

**Testing the API:** Use Postman (https://www.postman.com/downloads/) or any HTTP client to interact with the endpoints.

---

**Document Version:** 1.0  
**Last Updated:** January 2026  
**Laravel Version:** 11.9  
**PHP Version:** 8.2+
