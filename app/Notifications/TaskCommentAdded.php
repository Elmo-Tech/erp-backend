<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskCommentAdded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Task $task,
        public string $commenterName,
        public string $comment,
        public ?int $commenterId = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $projectName = $this->task->project?->title ?? __('No Project');
        $commentPreview = strlen($this->comment) > 150 
            ? substr($this->comment, 0, 150) . '...' 
            : $this->comment;

        return (new MailMessage)
            ->subject(__('New Comment on Task: :title', ['title' => $this->task->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':commenter added a comment on task ":task".', [
                'commenter' => $this->commenterName,
                'task' => $this->task->title,
            ]))
            ->line('**' . __('Task Details:') . '**')
            ->line(__('Task: :title', ['title' => $this->task->title]))
            ->line(__('Project: :project', ['project' => $projectName]))
            ->line('**' . __('Comment:') . '**')
            ->line($commentPreview)
            ->action(__('View Task'), url("/dashboard/tasks/{$this->task->id}"))
            ->line(__('Click the button above to view the full comment and reply.'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_comment_added',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'project_id' => $this->task->project_id,
            'project_name' => $this->task->project?->title,
            'commenter_id' => $this->commenterId,
            'commenter_name' => $this->commenterName,
            'comment' => $this->comment,
            'comment_preview' => strlen($this->comment) > 100 
                ? substr($this->comment, 0, 100) . '...' 
                : $this->comment,
            'commented_at' => now()->toISOString(),
            'message' => __(':commenter commented on task: :task', [
                'commenter' => $this->commenterName,
                'task' => $this->task->title,
            ]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
