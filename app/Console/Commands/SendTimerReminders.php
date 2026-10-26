<?php

namespace App\Console\Commands;

use App\Events\NotificationCreated;
use App\Jobs\SendWebPush;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SendTimerReminders extends Command
{
    protected $signature = 'time-entries:remind';

    protected $description = 'Send repeating reminders for work timers, independently of browser heartbeats';

    public function handle(): int
    {
        TimeEntry::whereNull('end_time')->where('trackable_type', Task::class)->chunkById(100, function ($entries): void {
            foreach ($entries as $entry) {
                DB::transaction(function () use ($entry): void {
                    $timer = TimeEntry::whereKey($entry->id)->lockForUpdate()->first();
                    $user = User::find($entry->user_id);
                    if (! $timer || $timer->end_time || ! $user?->is_active) {
                        return;
                    }
                    $interval = $user->timer_reminder_minutes ?? 15;
                    $last = $timer->getRawOriginal('last_reminded_at') ?? $timer->start_time;
                    if (Carbon::parse($last)->addMinutes($interval)->isFuture()) {
                        return;
                    }
                    DB::table('time_entries')->where('id', $timer->id)->update(['last_reminded_at' => now()]);
                    $payload = [
                        'type' => 'timer_reminder', 'message' => 'A work timer is still running',
                        'body' => $timer->trackable?->title ?? 'Open your timer to review your work session.',
                        'url' => '/dashboard/my-tasks', 'timer_id' => $timer->id,
                        'tag' => 'timer-'.$timer->id, 'reminderMinutes' => $interval,
                    ];
                    SendWebPush::dispatch($user->id, $payload, $timer->id)->afterCommit();
                    DB::afterCommit(fn () => event(new NotificationCreated($user->id, [
                        'id' => 'timer-'.$timer->id.'-'.now()->timestamp, 'data' => $payload,
                    ])));
                });
            }
        });

        return self::SUCCESS;
    }
}
