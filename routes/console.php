<?php

use App\Jobs\MarkOverdueInvoicesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the MarkOverdueInvoicesJob to run daily at midnight
// Requirements: 18.1, 18.5
Schedule::job(new MarkOverdueInvoicesJob)->daily();

// Create evaluation periods automatically on the 1st of odd months (Jan, Mar, May, Jul, Sep, Nov)
// This creates bi-monthly evaluation periods: Jan-Feb, Mar-Apr, May-Jun, Jul-Aug, Sep-Oct, Nov-Dec
Schedule::command('performance:create-period')
    ->monthlyOn(1, '00:00')
    ->when(function () {
        return in_array(now()->month, [1, 3, 5, 7, 9, 11]);
    })
    ->withoutOverlapping()
    ->onOneServer();
