<?php

namespace App\Modules\Notification\Notifications;

use App\Modules\Activity\Models\ActivityReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ActivityReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private ActivityReminder $reminder
    ) {}

    public function via(object $notifiable): array
    {
        return match ($this->reminder->channel) {
            'mail' => ['mail'],
            'database' => ['database'],
            'both' => ['mail', 'database'],
            default => ['database'],
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        $remindable = $this->reminder->remindable;
        $leadName = $remindable->lead->name ?? 'Unknown';

        return (new MailMessage)
            ->subject($this->reminder->title)
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line($this->reminder->message)
            ->line(__('notifications.lead_info', ['name' => $leadName]))
            ->action(__('notifications.view_details'), url('/leads/'.$remindable->lead_id))
            ->line(__('notifications.reminder_footer'));
    }

    public function toArray(object $notifiable): array
    {
        $remindable = $this->reminder->remindable;

        return [
            'type' => 'activity_reminder',
            'reminder_id' => $this->reminder->id,
            'title' => $this->reminder->title,
            'message' => $this->reminder->message,
            'remindable_type' => $this->reminder->remindable_type,
            'remindable_id' => $this->reminder->remindable_id,
            'lead_id' => $remindable->lead_id ?? null,
            'lead_name' => $remindable->lead->name ?? null,
            'scheduled_at' => $remindable->scheduled_at?->toISOString(),
        ];
    }
}
