<?php

use App\Http\Controllers\GoogleAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
});

Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);
