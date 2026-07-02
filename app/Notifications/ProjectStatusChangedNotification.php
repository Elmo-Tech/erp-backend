<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to team members when project status changes.
 */
class ProjectStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Project $project,
        public ?string $oldStatus,
        public string $newStatus
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $statusLabel = $this->getStatusLabel($this->newStatus);

        return (new MailMessage)
            ->subject(__('notifications.project_status_changed_subject', ['project' => $this->project->title]))
            ->greeting(__('notifications.hello', ['name' => $notifiable->name]))
            ->line(__('notifications.project_status_changed_body', [
                'project' => $this->project->title,
                'old_status' => $this->getStatusLabel($this->oldStatus),
                'new_status' => $statusLabel,
            ]))
            ->action(__('notifications.view_project'), url("/projects/{$this->project->id}"))
            ->line(__('notifications.thank_you'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'project_status_changed',
            'project_id' => $this->project->id,
            'project_title' => $this->project->title,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'message' => __('notifications.project_status_changed_short', [
                'project' => $this->project->title,
                'status' => $this->getStatusLabel($this->newStatus),
            ]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * Get human-readable status label.
     */
    private function getStatusLabel(?string $status): string
    {
        if (! $status) {
            return __('statuses.unknown');
        }

        $labels = [
            'draft' => __('statuses.draft'),
            'planning' => __('statuses.planning'),
            'ui_ux' => __('statuses.ui_ux'),
            'development' => __('statuses.development'),
            'testing' => __('statuses.testing'),
            'review' => __('statuses.review'),
            'completed' => __('statuses.completed'),
            'on_hold' => __('statuses.on_hold'),
            'cancelled' => __('statuses.cancelled'),
        ];

        return $labels[$status] ?? $status;
    }
}
