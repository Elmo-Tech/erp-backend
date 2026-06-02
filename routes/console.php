<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Modules\Finance\Models\Subscription;
use App\Modules\Notification\Notifications\SubscriptionCollectionDueNotification;
use App\Modules\Settings\Models\User;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('finance:notify-subscription-collections-due', function () {
    $subscriptions = Subscription::query()
        ->with(['client', 'project'])
        ->where('status', 'active')
        ->whereBetween('next_billing_date', [now()->toDateString(), now()->addDays(3)->toDateString()])
        ->get();

    if ($subscriptions->isEmpty()) {
        $this->info('No subscription collections due.');
        return 0;
    }

    $recipients = User::permission('collect_subscription_payments')->get();

    foreach ($subscriptions as $subscription) {
        foreach ($recipients as $recipient) {
            $recipient->notify(new SubscriptionCollectionDueNotification($subscription));
        }
    }

    $this->info("Queued {$subscriptions->count()} subscription collection notification batch(es).");
    return 0;
})->purpose('Notify finance users about subscription collections due soon');

Schedule::command('finance:notify-subscription-collections-due')->dailyAt('09:00');
