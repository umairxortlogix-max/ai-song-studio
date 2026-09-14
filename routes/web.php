<?php

use App\Http\Controllers\Admin\ProviderController;
use App\Http\Controllers\SongController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');

// Laravel Breeze/Fortify-style auth routes (register, login, logout,
// password reset, email verification) are expected to be published via:
//   composer require laravel/breeze --dev && php artisan breeze:install blade
// and are intentionally not re-implemented here.
require __DIR__.'/auth.php';

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [SongController::class, 'dashboard'])->name('dashboard');

    Route::get('/songs/create', [SongController::class, 'create'])->name('songs.create');
    Route::post('/songs', [SongController::class, 'store'])->name('songs.store');
    Route::get('/songs/{song}', [SongController::class, 'show'])->name('songs.show');
    Route::post('/songs/{song}/regenerate', [SongController::class, 'regenerate'])->name('songs.regenerate');
    Route::get('/songs/{song}/status', [SongController::class, 'status'])->name('songs.status');
    Route::get('/songs/{song}/download/{format}', [SongController::class, 'download'])->name('songs.download');
    Route::post('/songs/{song}/favorite', [SongController::class, 'toggleFavorite'])->name('songs.favorite');
    Route::delete('/songs/{song}', [SongController::class, 'destroy'])->name('songs.destroy');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/providers', [ProviderController::class, 'index'])->name('providers.index');
        Route::post('/providers', [ProviderController::class, 'store'])->name('providers.store');
        Route::put('/providers/{provider}', [ProviderController::class, 'update'])->name('providers.update');
        Route::post('/providers/{provider}/toggle', [ProviderController::class, 'toggle'])->name('providers.toggle');
        Route::post('/providers/{provider}/reset-usage', [ProviderController::class, 'resetUsage'])->name('providers.reset');
        Route::post('/providers/{provider}/test', [ProviderController::class, 'test'])->name('providers.test');
        Route::get('/providers/{provider}/logs', [ProviderController::class, 'logs'])->name('providers.logs');
    });
});
