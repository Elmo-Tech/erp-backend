<?php

namespace App\Modules\Notification\Notifications;

use App\Modules\Project\Models\ProjectTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectTaskAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ProjectTask $task
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $this->task->loadMissing('project:id,title');

        return (new MailMessage)
            ->subject('New task assigned: '.$this->task->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A new project task has been assigned to you.')
            ->line('Project: '.$this->task->project?->title)
            ->line('Task: '.$this->task->title)
            ->line('Priority: '.ucfirst((string) $this->task->priority))
            ->line('Due date: '.($this->task->due_date?->toDateString() ?? 'Not set'))
            ->action('Open Task', $this->frontendUrl('/dashboard/projects/'.$this->task->project_id.'?taskId='.$this->task->id));
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'project_task_assigned',
            'title' => 'New task assigned',
            'message' => $this->task->title,
            'project_id' => $this->task->project_id,
            'task_id' => $this->task->id,
            'priority' => $this->task->priority,
            'due_date' => $this->task->due_date?->toDateString(),
            'url' => '/dashboard/projects/'.$this->task->project_id.'?taskId='.$this->task->id,
        ];
    }

    private function frontendUrl(string $path): string
    {
        return rtrim((string) config('app.frontend_url', config('app.url')), '/').$path;
    }
}
