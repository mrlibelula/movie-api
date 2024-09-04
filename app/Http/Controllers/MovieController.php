<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MovieController extends Controller
{
    public function index()
    {
        $movies = Movie::all();
        return response()->json(['movies' => $movies]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'release_date' => 'required|date',
            'genre' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $movie = Movie::create($validator->validated());
            return response()->json($movie, 201);
        } catch (QueryException $e) {
            // Check if the error is due to a unique constraint violation
            if ($e->errorInfo[1] == 19) { // SQLite error code for unique constraint violation
                $errorMessage = sprintf(
                    "A movie with this title '%s' and release date '%s' already exists.",
                    $request->input('title'),
                    $request->input('release_date')
                );
                return response()->json([
                    'message' => 'Movie already exists',
                    'errors' => [
                        'title' => [$errorMessage]
                    ]
                ], 409); // 409 Conflict
            }
            
            // For other database errors
            return response()->json([
                'message' => 'An error occurred while saving the movie.',
                'errors' => ['database' => [$e->getMessage()]]
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $movie = Movie::findOrFail($id);
            return response()->json($movie);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Movie not found',
                'error' => 'The movie with ID ' . $id . ' does not exist.'
            ], 404);
        }
    }

    public function update(Request $request, Movie $movie)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'description' => 'string',
            'release_date' => 'date',
            'genre' => 'string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $movie->update($validator->validated());
        return response()->json(['movie' => $movie]);
    }

    public function destroy($id)
    {
        try {
            $movie = Movie::findOrFail($id);
            $deleted = $movie->delete();

            return response()->json([
                'message' => $deleted ? 'Movie successfully deleted' : 'Failed to delete movie',
                'data' => [
                    'deleted' => $deleted
                ]
            ], $deleted ? 200 : 500);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Movie not found',
                'data' => [
                    'deleted' => false
                ]
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while deleting the movie',
                'data' => [
                    'deleted' => false
                ],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function addToWatchLater(Movie $movie)
    {
        try {
            $user = auth()->user();
            
            // Check if the movie is already in the watch later list
            if ($user->watchLater()->where('movie_id', $movie->id)->exists()) {
                return response()->json([
                    'message' => "The movie '{$movie->title}' is already in your watch later list."
                ], 409); // 409 Conflict
            }

            $user->watchLater()->attach($movie->id);
            
            return response()->json([
                'message' => "The movie '{$movie->title}' has been added to your watch later list."
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Movie not found.',
                'error' => "The movie with ID {$movie->id} does not exist."
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while adding the movie to your watch later list.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function removeFromWatchLater(Movie $movie)
    {
        try {
            $user = auth()->user();
            
            // Check if the movie is in the watch later list
            if (!$user->watchLater()->where('movie_id', $movie->id)->exists()) {
                return response()->json([
                    'message' => "The movie '{$movie->title}' is not in your watch later list."
                ], 404);
            }

            $user->watchLater()->detach($movie->id);
            
            return response()->json([
                'message' => "The movie '{$movie->title}' has been removed from your watch later list."
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Movie not found.',
                'error' => "The movie with ID {$movie->id} does not exist."
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while removing the movie from your watch later list.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getWatchLaterList()
    {
        $user = auth()->user();
        $watchLaterMovies = $user->watchLater->makeHidden('pivot');

        return response()->json([
            'message' => 'Watch later list retrieved successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'watch_later' => [
                    'count' => $watchLaterMovies->count(),
                    'movies' => $watchLaterMovies
                ]
            ]
        ], 200);
    }
}
