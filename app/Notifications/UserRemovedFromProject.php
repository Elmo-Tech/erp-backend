<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserRemovedFromProject extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $projectTitle,
        public int $projectId,
        public string $removedByName,
        public ?int $removedById = null,
        public ?string $reason = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Removed from Project: :project', ['project' => $this->projectTitle]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':remover has removed you from the project ":project".', [
                'remover' => $this->removedByName,
                'project' => $this->projectTitle,
            ]))
            ->when($this->reason, function ($message) {
                return $message->line(__('Reason: :reason', ['reason' => $this->reason]));
            })
            ->line(__('You no longer have access to this project\'s tasks and resources.'))
            ->line(__('If you have any questions, please contact :remover or your project manager.', [
                'remover' => $this->removedByName,
            ]));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'user_removed_from_project',
            'project_id' => $this->projectId,
            'project_title' => $this->projectTitle,
            'removed_by_id' => $this->removedById,
            'removed_by_name' => $this->removedByName,
            'reason' => $this->reason,
            'removed_at' => now()->toISOString(),
            'message' => __(':remover removed you from project: :project', [
                'remover' => $this->removedByName,
                'project' => $this->projectTitle,
            ]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
