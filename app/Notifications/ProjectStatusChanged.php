<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Project $project,
        public string $oldStatus,
        public string $newStatus,
        public ?string $changedByName = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Project Status Updated'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('The status of project ":project" has been updated.', [
                'project' => $this->project->title,
            ]))
            ->line(__('Previous Status: :old', ['old' => $this->oldStatus]))
            ->line(__('New Status: :new', ['new' => $this->newStatus]))
            ->when($this->changedByName, function ($message) {
                return $message->line(__('Changed by: :name', ['name' => $this->changedByName]));
            })
            ->action(__('View Project'), url("/projects/{$this->project->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'project_status_changed',
            'project_id' => $this->project->id,
            'project_title' => $this->project->title,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'changed_by' => $this->changedByName,
            'message' => __('Project ":project" status changed from :old to :new', [
                'project' => $this->project->title,
                'old' => $this->oldStatus,
                'new' => $this->newStatus,
            ]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
