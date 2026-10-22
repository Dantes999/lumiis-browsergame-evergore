<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Remove old action logs and resolved battles outside the replay/audit window.
Schedule::command('model:prune')
    ->dailyAt('03:30')
    ->withoutOverlapping();
