<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MovieController extends Controller
{
    public function index()
    {
        $movies = Movie::all();
        return response()->json(['movies' => $movies]);
    }

    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'release_date' => 'required|date',
                'genre_id' => 'required|exists:genres,id',
            ]);

            $movie = Movie::create($validatedData);

            return response()->json($movie, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'A movie with this title and release date already exists.',
                    'errors' => ['title' => ['The combination of title and release date must be unique.']]
                ], 422);
            }
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Error creating movie: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred while creating the movie',
                'error' => $e->getMessage()
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
            
            if (!$user) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            if ($user->watchLater()->where('movie_id', $movie->id)->exists()) {
                return response()->json([
                    'message' => "The movie \"{$movie->title}\" is already in your watch later list."
                ], 409);
            }

            $user->watchLater()->attach($movie->id);
            
            return response()->json([
                'message' => "The movie \"{$movie->title}\" has been added to your watch later list."
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Server Error',
                'message' => 'An error occurred while processing your request.'
            ], 500);
        }
    }

    public function removeFromWatchLater(Movie $movie)
    {
        $user = auth()->user();
        
        if (!$user->watchLater()->where('movie_id', $movie->id)->exists()) {
            return response()->json([
                'message' => "The movie \"{$movie->title}\" is not in your watch later list."
            ], 404);
        }

        $user->watchLater()->detach($movie->id);
        
        return response()->json([
            'message' => "The movie \"{$movie->title}\" has been removed from your watch later list."
        ], 200);
    }

    public function getWatchLaterList()
    {
        $user = auth()->user();
        $watchLaterMovies = $user->watchLater()->with('genre')->get();

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
                    'movies' => $watchLaterMovies->map(function ($movie) {
                        return [
                            'id' => $movie->id,
                            'title' => $movie->title,
                            'description' => $movie->description,
                            'release_date' => $movie->release_date,
                            'genre' => [
                                'id' => $movie->genre->id,
                                'name' => $movie->genre->name,
                            ],
                        ];
                    }),
                ],
            ],
        ], 200);
    }
}
