<?php

use App\Modules\Project\Actions\PrivatizeProjectFilesAction;
use App\Modules\Reminder\Services\ReminderDispatchService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('finance:notify-subscription-collections-due', function () {
    $sent = app(ReminderDispatchService::class)->sendSubscriptionReminders();

    $this->info("Queued {$sent} subscription collection reminder(s).");

    return 0;
})->purpose('Notify finance users about subscription collections due soon');

Artisan::command('reminders:send-due {--days=2 : Number of days ahead for due-date reminders}', function () {
    $days = max(0, (int) $this->option('days'));
    $results = app(ReminderDispatchService::class)->sendDueReminders($days);

    foreach ($results as $type => $sent) {
        $this->line("{$type}: {$sent}");
    }

    $this->info('Due reminders processed.');

    return 0;
})->purpose('Send due email reminders for meetings, tasks, invoices, subscriptions, and client follow-ups');

Artisan::command('projects:privatize-files {--execute : Move verified files and remove public copies}', function () {
    $moveFiles = (bool) $this->option('execute');
    $result = app(PrivatizeProjectFilesAction::class)->execute($moveFiles);

    foreach ($result as $status => $count) {
        $this->line("{$status}: {$count}");
    }

    if (! $moveFiles && $result['pending'] > 0) {
        $this->warn('Dry run only. Re-run with --execute after reviewing these counts.');
    }

    return $result['missing'] > 0 || $result['failed'] > 0 ? 1 : 0;
})->purpose('Move legacy project files from public storage to private storage');

Schedule::command('finance:notify-subscription-collections-due')->dailyAt('09:00');
Schedule::command('reminders:send-due')->dailyAt('08:30');
