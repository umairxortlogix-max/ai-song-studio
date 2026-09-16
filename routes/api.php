<?php

use App\Http\Controllers\Admin\ProviderController;
use App\Http\Controllers\Api\SongApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/songs', [SongApiController::class, 'store']);
    Route::post('/songs/{song}/generate-lyrics', [SongApiController::class, 'generateLyrics']);
    Route::post('/songs/{song}/generate-music', [SongApiController::class, 'generateMusic']);
    Route::post('/songs/{song}/generate-vocals', [SongApiController::class, 'generateVocals']);
    Route::post('/songs/{song}/generate-full', [SongApiController::class, 'generateFull']);
    Route::post('/songs/{song}/regenerate', [SongApiController::class, 'regenerate']);
    Route::get('/songs/{song}/status', [SongApiController::class, 'status']);
    Route::get('/songs/{song}/download/{format}', [SongApiController::class, 'download']);
    Route::get('/providers', [SongApiController::class, 'providers']);

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/providers', [ProviderController::class, 'index']);
        Route::post('/providers', [ProviderController::class, 'store']);
        Route::put('/providers/{provider}', [ProviderController::class, 'update']);
        Route::post('/providers/{provider}/test', [ProviderController::class, 'test']);
    });
});
