<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('escrow:release')->everyMinute();

Artisan::command('products:sync-okeconnect', function () {
    $this->info('Starting product status synchronization from Okeconnect...');
    $result = app(\App\Services\OrderkuotaService::class)->syncProductStatuses();
    if ($result['success']) {
        $this->info($result['message']);
    } else {
        $this->error($result['message']);
    }
})->purpose('Sync product open/close status from Okeconnect price list page');

Schedule::command('products:sync-okeconnect')->everyFiveMinutes();
