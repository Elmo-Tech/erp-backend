<?php

namespace App\Modules\Notification\Notifications;

use App\Modules\Task\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Task $task,
        public string $oldStatus,
        public string $newStatus,
        public string $changedByName,
        public ?int $changedById = null,
        public ?string $comment = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $projectName = $this->task->project?->title ?? __('No Project');
        $priority = ucfirst($this->task->priority?->value ?? 'medium');

        return (new MailMessage)
            ->subject(__('Task Status Updated: :title', ['title' => $this->task->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':changer has updated the status of task ":task".', [
                'changer' => $this->changedByName,
                'task' => $this->task->title,
            ]))
            ->line('**'.__('Status Change:').'**')
            ->line(__('Previous Status: :old', ['old' => __(ucfirst(str_replace('_', ' ', $this->oldStatus)))]))
            ->line(__('New Status: :new', ['new' => __(ucfirst(str_replace('_', ' ', $this->newStatus)))]))
            ->line('**'.__('Task Details:').'**')
            ->line(__('Task: :title', ['title' => $this->task->title]))
            ->line(__('Project: :project', ['project' => $projectName]))
            ->line(__('Priority: :priority', ['priority' => $priority]))
            ->when($this->comment, function ($message) {
                return $message->line(__('Comment: :comment', ['comment' => $this->comment]));
            })
            ->action(__('View Task'), url("/dashboard/tasks/{$this->task->id}"))
            ->line(__('Please check the task for any required actions.'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_status_changed',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'task_priority' => $this->task->priority?->value,
            'project_id' => $this->task->project_id,
            'project_name' => $this->task->project?->title,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'old_status_label' => __(ucfirst(str_replace('_', ' ', $this->oldStatus))),
            'new_status_label' => __(ucfirst(str_replace('_', ' ', $this->newStatus))),
            'changed_by_id' => $this->changedById,
            'changed_by_name' => $this->changedByName,
            'comment' => $this->comment,
            'changed_at' => now()->toISOString(),
            'message' => __(':changer changed task ":task" status from :old to :new', [
                'changer' => $this->changedByName,
                'task' => $this->task->title,
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
