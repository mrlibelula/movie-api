<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MovieController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['throttle:api', 'auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::apiResource('movies', MovieController::class);
    Route::post('movies/{movie}/watch-later', [MovieController::class, 'addToWatchLater']);
    Route::delete('movies/{movie}/watch-later', [MovieController::class, 'removeFromWatchLater']);
    Route::get('/watch-later', [MovieController::class, 'getWatchLaterList']);
});

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
