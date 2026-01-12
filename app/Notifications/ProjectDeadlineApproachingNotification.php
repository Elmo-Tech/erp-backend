<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to team members when project deadline is approaching.
 */
class ProjectDeadlineApproachingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Project $project,
        public int $daysRemaining
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.deadline_approaching_subject', [
                'project' => $this->project->title,
                'days' => $this->daysRemaining,
            ]))
            ->greeting(__('notifications.hello', ['name' => $notifiable->name]))
            ->line(__('notifications.deadline_approaching_body', [
                'project' => $this->project->title,
                'days' => $this->daysRemaining,
                'deadline' => $this->project->deadline->format('Y-m-d'),
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
            'type' => 'project_deadline_approaching',
            'projectId' => $this->project->id,
            'projectTitle' => $this->project->title,
            'daysRemaining' => $this->daysRemaining,
            'deadline' => $this->project->deadline->format('Y-m-d'),
            'message' => __('notifications.deadline_approaching_short', [
                'project' => $this->project->title,
                'days' => $this->daysRemaining,
            ]),
        ];
    }
}
