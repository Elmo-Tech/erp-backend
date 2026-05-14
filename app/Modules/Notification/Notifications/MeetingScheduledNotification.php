<?php

namespace App\Modules\Notification\Notifications;

use App\Modules\Meetings\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MeetingScheduledNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Meeting $meeting
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'meeting_scheduled',
            'title' => 'Meeting scheduled',
            'message' => $this->meeting->title,
            'meeting_id' => $this->meeting->id,
            'scheduled_at' => $this->meeting->scheduled_at?->toISOString(),
            'url' => '/dashboard/calendar',
        ];
    }
}
