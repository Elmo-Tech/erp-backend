<?php

namespace App\Jobs;

use App\Models\ActivityReminder;
use App\Modules\Notification\Notifications\ActivityReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendActivityRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     * Sends all pending reminders that are due
     */
    public function handle(): void
    {
        $reminders = ActivityReminder::with(['user', 'remindable.lead'])
            ->pending()
            ->get();

        foreach ($reminders as $reminder) {
            try {
                // Send notification
                $reminder->user->notify(new ActivityReminderNotification($reminder));

                // Mark as sent
                $reminder->markAsSent();

                Log::info('Activity reminder sent', [
                    'reminder_id' => $reminder->id,
                    'user_id' => $reminder->user_id,
                    'type' => $reminder->type->value ?? $reminder->type,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send activity reminder', [
                    'reminder_id' => $reminder->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
