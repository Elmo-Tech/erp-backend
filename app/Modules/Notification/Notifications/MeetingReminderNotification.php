<?php

namespace App\Modules\Notification\Notifications;

use App\Modules\Meetings\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Meeting $meeting
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Meeting reminder: '.$this->meeting->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('You have an upcoming meeting scheduled.')
            ->line('Meeting: '.$this->meeting->title)
            ->line('Time: '.$this->meeting->scheduled_at?->toDayDateTimeString())
            ->action('Open Calendar', $this->frontendUrl('/dashboard/calendar'));
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'meeting_upcoming',
            'title' => 'Meeting reminder',
            'message' => $this->meeting->title,
            'meeting_id' => $this->meeting->id,
            'scheduled_at' => $this->meeting->scheduled_at?->toISOString(),
            'url' => '/dashboard/calendar',
        ];
    }

    private function frontendUrl(string $path): string
    {
        return rtrim((string) config('app.frontend_url', config('app.url')), '/').$path;
    }
}
