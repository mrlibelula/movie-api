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

### Authentication

#### Register a new user
- **POST** `/api/register`
- **Body**: 
  ```json
  {
    "name": "string",
    "email": "string",
    "password": "string",
    "password_confirmation": "string"
  }
  ```
- **Response**: User details and token

#### User login
- **POST** `/api/login`
- **Body**: 
  ```json
  {
    "email": "string",
    "password": "string"
  }
  ```
- **Response**: User details and token

### Movies

#### List all movies
- **GET** `/api/movies`
- **Response**: List of all movies

#### Create a new movie
- **POST** `/api/movies`
- **Body**: 
  ```json
  {
    "title": "string",
    "description": "string",
    "release_date": "YYYY-MM-DD",
    "genre_id": "integer"
  }
  ```
- **Response**: Created movie details

#### Get a specific movie
- **GET** `/api/movies/{id}`
- **Response**: Movie details

#### Update a movie
- **PUT** `/api/movies/{id}`
- **Body**: 
  ```json
  {
    "title": "string",
    "description": "string",
    "release_date": "YYYY-MM-DD",
    "genre_id": "integer"
  }
  ```
- **Response**: Updated movie details

#### Delete a movie
- **DELETE** `/api/movies/{id}`
- **Response**: Success message

### Watch Later

#### Add a movie to watch later list
- **POST** `/api/watchlater/{movie_id}`
- **Response**: Success message

#### Remove a movie from watch later list
- **DELETE** `/api/watchlater/{movie_id}`
- **Response**: Success message

#### Get user's watch later list
- **GET** `/api/watchlater`
- **Response**: List of movies in the user's watch later list

### Authentication Note

All endpoints except `/api/register` and `/api/login` require authentication. Include the bearer token in the Authorization header:

```
Authorization: Bearer {token}
```

### Error Responses

All endpoints may return the following error responses:

- `400 Bad Request`: Invalid input data
- `401 Unauthorized`: Invalid or missing authentication token
- `403 Forbidden`: User doesn't have permission to perform the action
- `404 Not Found`: Requested resource not found
- `422 Unprocessable Entity`: Validation errors
- `500 Internal Server Error`: Server-side error

## Testing

Run the test suite with:
```
php artisan test
```

## Live Demo

You can access the live demo of this project using the following base URL and endpoints:

Base URL: `https://libe.dev/demo/movie-api/v1/api`

Endpoints:
- `/register`
- `/login`
- `/movies`
- `/movies/{id}`
- `/logout`
- `/movies/{movie}/watch-later`
- `/watch-later`

Example full URL: `https://libe.dev/demo/movie-api/v1/api/register`

Please note that these endpoints are for demonstration purposes only. Refer to the API documentation for full details on request/response formats and authentication requirements.

## Testing the API

To interact with these endpoints, we recommend using Postman, a popular API development and testing tool. 

You can download Postman here: [https://www.postman.com/downloads/](https://www.postman.com/downloads/)

Postman allows you to easily send requests to the API endpoints and view the responses, making it an ideal tool for exploring and testing the functionality of this Movie API.

[libe.dev](https://libe.dev)