<?php

namespace App\Modules\Notification\Notifications;

use App\Modules\PersonalTasks\Models\PersonalTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PersonalTaskDueReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly PersonalTask $task
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Personal task due soon: '.$this->task->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A personal task is due soon.')
            ->line('Task: '.$this->task->title)
            ->line('Priority: '.ucfirst((string) $this->task->priority))
            ->line('Due date: '.$this->task->due_date?->toDateString())
            ->action('Open My Tasks', $this->frontendUrl('/dashboard/my-tasks'));
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'personal_task_due',
            'title' => 'Personal task due soon',
            'message' => $this->task->title,
            'personal_task_id' => $this->task->id,
            'task_id' => $this->task->id,
            'priority' => $this->task->priority,
            'due_date' => $this->task->due_date?->toDateString(),
            'url' => '/dashboard/my-tasks',
        ];
    }

    private function frontendUrl(string $path): string
    {
        return rtrim((string) config('app.frontend_url', config('app.url')), '/').$path;
    }
}
