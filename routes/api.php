<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MovieController;
use App\Http\Middleware\EnsureValidToken;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware([EnsureValidToken::class, 'auth:sanctum'])->group(function () {
    Route::post('/movies', [MovieController::class, 'store']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::apiResource('movies', MovieController::class);
    Route::post('movies/{movie}/watch-later', [MovieController::class, 'addToWatchLater']);
    Route::delete('movies/{movie}/watch-later', [MovieController::class, 'removeFromWatchLater']);
    Route::get('/watch-later', [MovieController::class, 'getWatchLaterList']);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/movies', [MovieController::class, 'store'])->middleware('auth:sanctum');
