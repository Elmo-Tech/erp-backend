<?php

namespace App\Notifications;

use App\Models\LeadMeeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private LeadMeeting $meeting,
        private string $type = 'before' // before or after
    ) {}

    public function via(object $notifiable): array
    {
        return $this->type === 'before' ? ['mail', 'database'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('meetings.reminder_subject', ['title' => $this->meeting->title]))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('meetings.reminder_line', [
                'title' => $this->meeting->title,
                'time' => $this->meeting->scheduled_at->format('H:i'),
                'date' => $this->meeting->scheduled_at->format('Y-m-d'),
            ]));

        if ($this->meeting->meeting_link) {
            $mail->action(__('meetings.join_meeting'), $this->meeting->meeting_link);
        }

        return $mail->line(__('meetings.lead_info', ['name' => $this->meeting->lead->name]));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type === 'before' ? 'meeting_reminder' : 'meeting_outcome_reminder',
            'meeting_id' => $this->meeting->id,
            'title' => $this->meeting->title,
            'lead_id' => $this->meeting->lead_id,
            'lead_name' => $this->meeting->lead->name ?? null,
            'scheduled_at' => $this->meeting->scheduled_at->toISOString(),
            'location' => $this->meeting->location->value ?? $this->meeting->location,
            'meeting_link' => $this->meeting->meeting_link,
        ];
    }
}
