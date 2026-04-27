<?php

namespace App\Notifications;

use App\Modules\Task\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

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
        $this->task->loadMissing('assignees');

        $projectName = $this->task->project?->title ?? __('No Project');
        $priority = ucfirst($this->task->priority?->value ?? 'medium');
        $dueDate = $this->task->due_date ? $this->task->due_date->format('Y-m-d') : __('Not set');
        $expectedResolutionDate = $this->task->expected_resolution_date?->format('Y-m-d');
        $isDirectAssignee = method_exists($notifiable, 'getKey')
            && $this->task->assignees->contains(fn ($assignee) => $assignee->user_id === $notifiable->getKey());

        $summaryLine = $isDirectAssignee
            ? __(':assigner has assigned you to a new task.', ['assigner' => $this->assignerName])
            : __(':assigner assigned task ":task".', ['assigner' => $this->assignerName, 'task' => $this->task->title]);

        return (new MailMessage)
            ->subject(__('New Task Assigned: :title', ['title' => $this->task->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name ?? __('there')]))
            ->line($summaryLine)
            ->line('**'.__('Task Details:').'**')
            ->line(__('Task: :title', ['title' => $this->task->title]))
            ->line(__('Project: :project', ['project' => $projectName]))
            ->line(__('Priority: :priority', ['priority' => $priority]))
            ->line(__('Due Date: :date', ['date' => $dueDate]))
            ->when($expectedResolutionDate, function ($message) use ($expectedResolutionDate) {
                return $message->line(__('Expected Resolution: :date', ['date' => $expectedResolutionDate]));
            })
            ->when($this->task->description, function ($message) {
                return $message->line(__('Description: :description', [
                    'description' => Str::limit($this->task->description, 150),
                ]));
            })
            ->action(__('View Task'), url("/dashboard/tasks/{$this->task->id}"))
            ->line($isDirectAssignee
                ? __('Please review the task details and start working on it.')
                : __('You are receiving this update because you are subscribed to task notifications.'));
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
            'expected_resolution_date' => $this->task->expected_resolution_date?->toISOString(),
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
