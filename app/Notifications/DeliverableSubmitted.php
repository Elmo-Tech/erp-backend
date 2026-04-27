<?php

namespace App\Notifications;

use App\Modules\Task\Models\TaskDeliverable;
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
        public string $developerName
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
            ->subject(__('New Deliverable Submitted: :name', ['name' => $this->deliverable->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':developer has submitted a new deliverable for review! 📋', ['developer' => $this->developerName]))
            ->line('**'.__('Deliverable Details:').'**')
            ->line(__('Deliverable: :name', ['name' => $this->deliverable->title]))
            ->line(__('Task: :task', ['task' => $taskTitle]))
            ->line(__('Project: :project', ['project' => $projectName]))
            ->line(__('Submitted At: :time', ['time' => $this->deliverable->submitted_at?->format('Y-m-d H:i')]))
            ->when($this->deliverable->submission_notes, function ($message) {
                return $message->line('**'.__('Submission Notes:').'**')
                    ->line($this->deliverable->submission_notes);
            })
            ->action(__('Review Deliverable'), url("/dashboard/tasks/{$this->deliverable->task_id}"))
            ->line(__('Please review this deliverable at your earliest convenience.'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'deliverable_submitted',
            'deliverable_id' => $this->deliverable->id,
            'deliverable_name' => $this->deliverable->title,
            'task_id' => $this->deliverable->task_id,
            'task_title' => $this->deliverable->task?->title,
            'project_id' => $this->deliverable->task?->project_id,
            'project_name' => $this->deliverable->task?->project?->title,
            'developer_name' => $this->developerName,
            'submitted_at' => $this->deliverable->submitted_at?->toISOString(),
            'submission_notes' => $this->deliverable->submission_notes,
            'is_bonus' => $this->deliverable->is_bonus,
            'message' => __(':developer submitted deliverable ":name" for review 📋', [
                'developer' => $this->developerName,
                'name' => $this->deliverable->title,
            ]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
