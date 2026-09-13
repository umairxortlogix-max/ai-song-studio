<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Periodic provider health checks — keeps ai_providers.status current
// so the router always knows which providers are actually reachable.
Schedule::command('ai:health-check')->everyFiveMinutes()->withoutOverlapping();

// Belt-and-braces daily/monthly counter reset (also called from within
// ai:health-check, but scheduled independently too in case that command
// is ever removed or fails).
Schedule::command('ai:reset-usage')->dailyAt('00:00');
