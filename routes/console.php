<?php

use App\Jobs\CheckQuotationExpiryJob;
use App\Jobs\CheckSubscriptionRenewalJob;
use App\Jobs\GenerateRecurringTasksJob;
use App\Jobs\MarkOverdueInvoicesJob;
use App\Jobs\SendActivityRemindersJob;
use App\Jobs\SendExpiryWarningsJob;
use App\Jobs\SyncDeviceEventsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Minishlink\WebPush\VAPID;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notifications:vapid', function () {
    $keys = VAPID::createVapidKeys();
    $this->line('WEB_PUSH_PUBLIC_KEY='.$keys['publicKey']);
    $this->line('WEB_PUSH_PRIVATE_KEY='.$keys['privateKey']);
})->purpose('Generate Web Push VAPID keys once; store these in the server environment');

Schedule::command('time-entries:remind')->everyMinute()->withoutOverlapping()->onOneServer();

// Schedule the MarkOverdueInvoicesJob to run daily at midnight
// Requirements: 18.1, 18.5
if (! config('simplification.enabled')) {
    Schedule::job(new MarkOverdueInvoicesJob)->daily();
}

// Generate recurring tasks daily at 00:05 (after midnight)
Schedule::job(new GenerateRecurringTasksJob)
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->onOneServer();

// Check for expiring quotations daily at 8 AM (3-day warning)
if (! config('simplification.enabled')) {
    Schedule::job(new CheckQuotationExpiryJob)->dailyAt('08:00')->withoutOverlapping();
}

// Check for subscription renewals daily at 7 AM
// Sends reminders at 30, 7, and 1 day before expiry
if (! config('simplification.enabled')) {
    Schedule::job(new CheckSubscriptionRenewalJob)->dailyAt('07:00')->withoutOverlapping();
}

// Send activity reminders every 5 minutes
if (! config('simplification.enabled')) {
    Schedule::job(new SendActivityRemindersJob)->everyFiveMinutes()->withoutOverlapping();
}

// Send expiry warnings daily at 9 AM
if (! config('simplification.enabled')) {
    Schedule::job(new SendExpiryWarningsJob(2))->dailyAt('09:00')->withoutOverlapping();
}

// Create evaluation periods automatically on the 1st of odd months (Jan, Mar, May, Jul, Sep, Nov)
// This creates bi-monthly evaluation periods: Jan-Feb, Mar-Apr, May-Jun, Jul-Aug, Sep-Oct, Nov-Dec
if (! config('simplification.enabled')) {
    Schedule::command('performance:create-period')
        ->monthlyOn(1, '00:00')
        ->when(function () {
            return in_array(now()->month, [1, 3, 5, 7, 9, 11]);
        })
        ->withoutOverlapping()
        ->onOneServer();
}

// Reset grace days for all employees on the 1st of every month at 00:01
Schedule::command('attendance:reset-grace-days')
    ->monthlyOn(1, '00:01')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('time-entries:expire-stale')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// Poll the access control terminals for new events.
if (config('devices.polling.enabled')) {
    Schedule::job(new SyncDeviceEventsJob)
        ->everyFiveMinutes()
        ->withoutOverlapping()
        ->onOneServer();
}
