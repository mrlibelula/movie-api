# Movie API

A Laravel-based RESTful API for managing movies and user watch lists.

## Features

- User authentication
- CRUD operations for movies
- Watch later functionality

## Requirements

- PHP 8.1+
- Composer
- Laravel 10.x
- SQLite 3

## Installation

1. Clone the repository:
   ```
   git clone https://github.com/mrlibelula/movie-api.git
   cd movie-api
   ```

2. Install dependencies:
   ```
   composer install
   ```

3. Copy the .env.example file to .env and configure your database:
   ```
   cp .env.example .env
   ```

4. Update the .env file to use SQLite:
   ```
   DB_CONNECTION=sqlite
   DB_DATABASE=/absolute/path/to/your/database.sqlite
   ```

5. Create an empty SQLite database file:
   ```
   touch database/database.sqlite
   ```

6. Generate application key:
   ```
   php artisan key:generate
   ```

7. Run migrations:
   ```
   php artisan migrate
   ```

8. [Optional] Seed the database:
   ```
   php artisan db:seed
   ```

## Usage

Start the development server:
```
php artisan serve
```

You can now access the API at `http://localhost:8000`.


The API will be available at `http://localhost:8000`.

## API Endpoints

- `POST /api/register` - Register a new user
- `POST /api/login` - User login
- `GET /api/movies` - List all movies
- `POST /api/movies` - Create a new movie
- `GET /api/movies/{id}` - Get a specific movie
- `PUT /api/movies/{id}` - Update a movie
- `DELETE /api/movies/{id}` - Delete a movie
- `POST /api/watchlater/{movie_id}` - Add a movie to watch later list
- `DELETE /api/watchlater/{movie_id}` - Remove a movie from watch later list
- `GET /api/watchlater` - Get user's watch later list

## Testing

Run the test suite with:
```
php artisan test
```

[libe.dev](https://libe.dev)