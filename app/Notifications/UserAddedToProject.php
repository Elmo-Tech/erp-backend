<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserAddedToProject extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Project $project,
        public string $addedByName,
        public ?int $addedById = null,
        public ?string $role = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $clientName = $this->project->client?->name ?? __('No Client');
        $deadline = $this->project->deadline ? $this->project->deadline->format('Y-m-d') : __('Not set');

        return (new MailMessage)
            ->subject(__('Added to Project: :project', ['project' => $this->project->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':adder has added you to the project ":project".', [
                'adder' => $this->addedByName,
                'project' => $this->project->title,
            ]))
            ->line('**'.__('Project Details:').'**')
            ->line(__('Project: :title', ['title' => $this->project->title]))
            ->line(__('Client: :client', ['client' => $clientName]))
            ->line(__('Status: :status', ['status' => __(ucfirst(str_replace('_', ' ', $this->project->status?->value ?? 'draft')))]))
            ->line(__('Deadline: :deadline', ['deadline' => $deadline]))
            ->when($this->role, function ($message) {
                return $message->line(__('Your Role: :role', ['role' => $this->role]));
            })
            ->when($this->project->description, function ($message) {
                return $message->line(__('Description: :description', [
                    'description' => substr($this->project->description, 0, 150).'...',
                ]));
            })
            ->action(__('View Project'), url("/dashboard/projects/{$this->project->id}"))
            ->line(__('Welcome to the team! Please review the project details.'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'user_added_to_project',
            'project_id' => $this->project->id,
            'project_title' => $this->project->title,
            'project_status' => $this->project->status?->value,
            'client_id' => $this->project->client_id,
            'client_name' => $this->project->client?->name,
            'added_by_id' => $this->addedById,
            'added_by_name' => $this->addedByName,
            'role' => $this->role,
            'deadline' => $this->project->deadline?->toISOString(),
            'added_at' => now()->toISOString(),
            'message' => __(':adder added you to project: :project', [
                'adder' => $this->addedByName,
                'project' => $this->project->title,
            ]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
