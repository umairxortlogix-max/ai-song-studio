<?php

namespace App\Providers;

use App\Models\Song;
use App\Policies\SongPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Song::class, SongPolicy::class);
    }
}
