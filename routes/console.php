<?php

use App\Console\Commands\MultiChainSetup;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('multichain:setup', function () {
    $this->call(MultiChainSetup::class);
})->purpose('Setup MultiChain streams, address and permissions');
