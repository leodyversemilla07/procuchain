<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Removed manual multichain:setup command wrapper to allow the
// dedicated Command class signature options to be registered.

// Scheduled node health check — auto-repairs unsubscribed nodes every 6 hours
use Illuminate\Support\Facades\Schedule;

Schedule::command('multichain:node-health --fix --notify')->everySixHours()->withoutOverlapping();

// Clean up orphaned temp files from blockchain uploads every hour
Schedule::command('temp:cleanup --hours=1')->hourly()->withoutOverlapping();
