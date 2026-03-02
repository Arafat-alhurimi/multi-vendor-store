<?php

use App\Console\Commands\DeactivateExpiredPromotionsAndDiscounts;
use App\Console\Commands\ExpireVendorAdSubscriptions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(DeactivateExpiredPromotionsAndDiscounts::class)->everyMinute();
Schedule::command(ExpireVendorAdSubscriptions::class)->daily();
