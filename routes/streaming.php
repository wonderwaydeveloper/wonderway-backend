<?php

use App\Http\Controllers\Api\StreamingController;
use Illuminate\Support\Facades\Route;

// Streaming routes
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('streaming')->group(function () {
        Route::post('/create', [StreamingController::class, 'create']);
        Route::post('/start', [StreamingController::class, 'start']);
        Route::post('/end', [StreamingController::class, 'end']);
        Route::get('/live', [StreamingController::class, 'live']);
        Route::get('/my-streams', [StreamingController::class, 'myStreams']);
        Route::get('/{stream}', [StreamingController::class, 'show']);
        Route::delete('/{stream}', [StreamingController::class, 'delete']);
        Route::post('/{streamKey}/join', [StreamingController::class, 'join']);
        Route::post('/{streamKey}/leave', [StreamingController::class, 'leave']);
        Route::get('/{streamKey}/stats', [StreamingController::class, 'stats']);
    });
});

// Webhook routes (no auth required)
Route::prefix('streaming')->group(function () {
    Route::post('/auth', [StreamingController::class, 'auth']);
    Route::post('/publish-done', [StreamingController::class, 'publishDone']);
    Route::post('/play', [StreamingController::class, 'play']);
    Route::post('/play-done', [StreamingController::class, 'playDone']);
});
