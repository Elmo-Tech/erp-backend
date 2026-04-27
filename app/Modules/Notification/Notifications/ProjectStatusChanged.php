<?php

namespace App\Modules\Notification\Notifications;

use App\Modules\Project\Models\Project;
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
        public ?string $changedByName = null,
        public ?int $changedById = null,
        public ?string $reason = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $clientName = $this->project->client?->name ?? __('No Client');

        return (new MailMessage)
            ->subject(__('Project Status Updated: :project', ['project' => $this->project->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('The status of project ":project" has been updated.', [
                'project' => $this->project->title,
            ]))
            ->line('**'.__('Status Change:').'**')
            ->line(__('Previous Status: :old', ['old' => __(ucfirst(str_replace('_', ' ', $this->oldStatus)))]))
            ->line(__('New Status: :new', ['new' => __(ucfirst(str_replace('_', ' ', $this->newStatus)))]))
            ->line('**'.__('Project Details:').'**')
            ->line(__('Client: :client', ['client' => $clientName]))
            ->when($this->project->deadline, function ($message) {
                return $message->line(__('Deadline: :deadline', [
                    'deadline' => $this->project->deadline->format('Y-m-d'),
                ]));
            })
            ->when($this->changedByName, function ($message) {
                return $message->line(__('Changed by: :name', ['name' => $this->changedByName]));
            })
            ->when($this->reason, function ($message) {
                return $message->line(__('Reason: :reason', ['reason' => $this->reason]));
            })
            ->action(__('View Project'), url("/dashboard/projects/{$this->project->id}"))
            ->line(__('Please check the project for any required actions.'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'project_status_changed',
            'project_id' => $this->project->id,
            'project_title' => $this->project->title,
            'client_id' => $this->project->client_id,
            'client_name' => $this->project->client?->name,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'old_status_label' => __(ucfirst(str_replace('_', ' ', $this->oldStatus))),
            'new_status_label' => __(ucfirst(str_replace('_', ' ', $this->newStatus))),
            'changed_by_id' => $this->changedById,
            'changed_by_name' => $this->changedByName,
            'reason' => $this->reason,
            'changed_at' => now()->toISOString(),
            'message' => $this->changedByName
                ? __(':changer changed project ":project" status from :old to :new', [
                    'changer' => $this->changedByName,
                    'project' => $this->project->title,
                    'old' => __(ucfirst(str_replace('_', ' ', $this->oldStatus))),
                    'new' => __(ucfirst(str_replace('_', ' ', $this->newStatus))),
                ])
                : __('Project ":project" status changed from :old to :new', [
                    'project' => $this->project->title,
                    'old' => __(ucfirst(str_replace('_', ' ', $this->oldStatus))),
                    'new' => __(ucfirst(str_replace('_', ' ', $this->newStatus))),
                ]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
