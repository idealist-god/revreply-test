<?php

use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [UserController::class, 'register']);
Route::post('/auth/login',    [UserController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout',        [UserController::class, 'logout']);
    Route::get('/auth/me',             [UserController::class, 'me']);
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
});

// google cloud console registered
Route::get('/auth/google/callback',  [GoogleAuthController::class,  'callback']);
