<?php

namespace App\Notifications;

use App\Models\TaskDeliverable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeliverableSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public TaskDeliverable $deliverable,
        public string $submitterName,
        public ?int $submitterId = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $taskTitle = $this->deliverable->task?->title ?? __('Unknown Task');
        $projectName = $this->deliverable->task?->project?->title ?? __('No Project');

        return (new MailMessage)
            ->subject(__('Deliverable Submitted for Review: :name', ['name' => $this->deliverable->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':submitter has submitted a deliverable for your review.', [
                'submitter' => $this->submitterName,
            ]))
            ->line('**' . __('Deliverable Details:') . '**')
            ->line(__('Deliverable: :name', ['name' => $this->deliverable->name]))
            ->line(__('Task: :task', ['task' => $taskTitle]))
            ->line(__('Project: :project', ['project' => $projectName]))
            ->when($this->deliverable->description, function ($message) {
                return $message->line(__('Description: :description', [
                    'description' => substr($this->deliverable->description, 0, 150) . '...'
                ]));
            })
            ->when($this->deliverable->file_path, function ($message) {
                return $message->line(__('File attached: Yes'));
            })
            ->action(__('Review Deliverable'), url("/dashboard/tasks/{$this->deliverable->task_id}"))
            ->line(__('Please review and approve or reject this deliverable.'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'deliverable_submitted',
            'deliverable_id' => $this->deliverable->id,
            'deliverable_name' => $this->deliverable->name,
            'task_id' => $this->deliverable->task_id,
            'task_title' => $this->deliverable->task?->title,
            'project_id' => $this->deliverable->task?->project_id,
            'project_name' => $this->deliverable->task?->project?->title,
            'submitter_id' => $this->submitterId,
            'submitter_name' => $this->submitterName,
            'has_file' => !empty($this->deliverable->file_path),
            'submitted_at' => $this->deliverable->submitted_at?->toISOString(),
            'message' => __(':submitter submitted deliverable ":name" for review', [
                'submitter' => $this->submitterName,
                'name' => $this->deliverable->name,
            ]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
