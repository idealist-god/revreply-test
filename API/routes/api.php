<?php

use App\Http\Controllers\DraftController;
use App\Http\Controllers\GmailAccountController;
use App\Http\Controllers\GmailWebhookController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\ThreadController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [UserController::class, 'register']);
Route::post('/auth/login',    [UserController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout',         [UserController::class,       'logout']);
    Route::get('/auth/me',              [UserController::class,       'me']);
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);

    Route::get('/accounts',             [GmailAccountController::class, 'index']);
    Route::delete('/accounts/{id}',     [GmailAccountController::class, 'destroy']);

    Route::get('/threads',              [ThreadController::class, 'index']);
    Route::get('/threads/{id}',         [ThreadController::class, 'show']);
    Route::patch('/threads/{id}/dismiss', [ThreadController::class, 'dismiss']);

    Route::put('/drafts/{id}',          [DraftController::class, 'update']);
    Route::post('/drafts/{id}/approve', [DraftController::class, 'approve']);
    Route::post('/drafts/{id}/reject',  [DraftController::class, 'reject']);
    Route::post('/drafts/{id}/regenerate', [DraftController::class, 'regenerate']);
});

// google cloud console registered
Route::get('/auth/google/callback',  [GoogleAuthController::class,  'callback']);
Route::post('/gmail/webhook',        [GmailWebhookController::class, 'handle']);
