<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DraftController;
use App\Http\Controllers\Api\GmailController;
use App\Http\Controllers\Api\ThreadController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/gmail/callback', [GmailController::class, 'callback']);
Route::get('/gmail/status', [GmailController::class, 'status']);
Route::post('/webhooks/gmail/pubsub', [WebhookController::class, 'pubsub']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/gmail/connect', [GmailController::class, 'connect']);
    Route::get('/gmail/accounts', [GmailController::class, 'accounts']);
    Route::post('/gmail/accounts/sync-all', [GmailController::class, 'syncAll']);
    Route::delete('/gmail/accounts/{gmailAccount}', [GmailController::class, 'destroy']);
    Route::post('/gmail/accounts/{gmailAccount}/sync', [GmailController::class, 'sync']);

    Route::get('/threads', [ThreadController::class, 'index']);
    Route::get('/threads/{thread}', [ThreadController::class, 'show']);
    Route::get('/messages/{message}', [ThreadController::class, 'message']);
    Route::post('/messages/{message}/generate-draft', [ThreadController::class, 'generateDraft']);
    Route::post('/messages/{message}/process', [ThreadController::class, 'process']);

    Route::post('/drafts/{draft}/approve', [DraftController::class, 'approve']);
    Route::post('/drafts/{draft}/reject', [DraftController::class, 'reject']);

    Route::get('/settings', [AuthController::class, 'settings']);
    Route::put('/settings/reply-prompt', [AuthController::class, 'updateReplyPrompt']);
});
