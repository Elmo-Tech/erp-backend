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
