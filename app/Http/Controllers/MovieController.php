<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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

        $movie = Movie::create($validator->validated());
        return response()->json(['movie' => $movie], 201);
    }

    public function show(Movie $movie)
    {
        return response()->json(['movie' => $movie]);
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

    public function destroy(Movie $movie)
    {
        $movie->delete();
        return response()->json(null, 204);
    }

    public function addToWatchLater(Movie $movie)
    {
        auth()->user()->watchLater()->attach($movie->id);
        return response()->json(['message' => 'Movie added to watch later list']);
    }

    public function removeFromWatchLater(Movie $movie)
    {
        auth()->user()->watchLater()->detach($movie->id);
        return response()->json(['message' => 'Movie removed from watch later list']);
    }
}
