<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Task $task,
        public string $assignerName,
        public ?int $assignerId = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $projectName = $this->task->project?->title ?? __('No Project');
        $priority = ucfirst($this->task->priority?->value ?? 'medium');
        $dueDate = $this->task->due_date ? $this->task->due_date->format('Y-m-d') : __('Not set');

        return (new MailMessage)
            ->subject(__('New Task Assigned: :title', ['title' => $this->task->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':assigner has assigned you to a new task.', ['assigner' => $this->assignerName]))
            ->line('**' . __('Task Details:') . '**')
            ->line(__('Task: :title', ['title' => $this->task->title]))
            ->line(__('Project: :project', ['project' => $projectName]))
            ->line(__('Priority: :priority', ['priority' => $priority]))
            ->line(__('Due Date: :date', ['date' => $dueDate]))
            ->when($this->task->description, function ($message) {
                return $message->line(__('Description: :description', [
                    'description' => substr($this->task->description, 0, 150) . '...'
                ]));
            })
            ->action(__('View Task'), url("/dashboard/tasks/{$this->task->id}"))
            ->line(__('Please review the task details and start working on it.'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_assigned',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'task_priority' => $this->task->priority?->value,
            'task_status' => $this->task->status?->value,
            'project_id' => $this->task->project_id,
            'project_name' => $this->task->project?->title,
            'assigner_id' => $this->assignerId,
            'assigner_name' => $this->assignerName,
            'due_date' => $this->task->due_date?->toISOString(),
            'assigned_at' => now()->toISOString(),
            'message' => __(':assigner assigned you to task: :title', [
                'assigner' => $this->assignerName,
                'title' => $this->task->title,
            ]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
