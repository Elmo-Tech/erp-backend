<?php

namespace App\Modules\Notification\Notifications;

use App\Modules\Project\Models\ProjectTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectTaskDueReminderNotification extends Notification implements ShouldQueue
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
            ->subject('Task due soon: '.$this->task->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A task assigned to you is due soon.')
            ->line('Project: '.$this->task->project?->title)
            ->line('Task: '.$this->task->title)
            ->line('Due date: '.$this->task->due_date?->toDateString())
            ->action('Open Project', $this->frontendUrl('/dashboard/projects/'.$this->task->project_id));
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'project_task_due',
            'title' => 'Task due soon',
            'message' => $this->task->title,
            'project_id' => $this->task->project_id,
            'task_id' => $this->task->id,
            'due_date' => $this->task->due_date?->toDateString(),
            'url' => '/dashboard/projects/'.$this->task->project_id,
        ];
    }

    private function frontendUrl(string $path): string
    {
        return rtrim((string) config('app.frontend_url', config('app.url')), '/').$path;
    }
}
