<?php

namespace App\Notifications;

use App\Modules\Task\Models\TaskDeliverable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeliverableReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public TaskDeliverable $deliverable,
        public string $reviewerName,
        public string $status, // 'approved' or 'rejected'
        public ?int $reviewerId = null,
        public ?string $reviewNotes = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $taskTitle = $this->deliverable->task?->title ?? __('Unknown Task');
        $projectName = $this->deliverable->task?->project?->title ?? __('No Project');
        $isApproved = $this->status === 'approved';

        $message = (new MailMessage)
            ->subject($isApproved
                ? __('Deliverable Approved: :name', ['name' => $this->deliverable->name])
                : __('Deliverable Rejected: :name', ['name' => $this->deliverable->name])
            )
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line($isApproved
                ? __(':reviewer has approved your deliverable! 🎉', ['reviewer' => $this->reviewerName])
                : __(':reviewer has rejected your deliverable and requested changes.', ['reviewer' => $this->reviewerName])
            )
            ->line('**'.__('Deliverable Details:').'**')
            ->line(__('Deliverable: :name', ['name' => $this->deliverable->name]))
            ->line(__('Task: :task', ['task' => $taskTitle]))
            ->line(__('Project: :project', ['project' => $projectName]))
            ->line(__('Status: :status', ['status' => $isApproved ? __('Approved') : __('Rejected')]));

        if ($this->reviewNotes) {
            $message->line('**'.__('Reviewer Notes:').'**')
                ->line($this->reviewNotes);
        }

        return $message
            ->action(__('View Task'), url("/dashboard/tasks/{$this->deliverable->task_id}"))
            ->line($isApproved
                ? __('Great work! Keep up the excellent performance.')
                : __('Please review the feedback and resubmit the deliverable.')
            );
    }

    public function toArray(object $notifiable): array
    {
        $isApproved = $this->status === 'approved';

        return [
            'type' => 'deliverable_reviewed',
            'deliverable_id' => $this->deliverable->id,
            'deliverable_name' => $this->deliverable->name,
            'task_id' => $this->deliverable->task_id,
            'task_title' => $this->deliverable->task?->title,
            'project_id' => $this->deliverable->task?->project_id,
            'project_name' => $this->deliverable->task?->project?->title,
            'reviewer_id' => $this->reviewerId,
            'reviewer_name' => $this->reviewerName,
            'status' => $this->status,
            'is_approved' => $isApproved,
            'review_notes' => $this->reviewNotes,
            'reviewed_at' => $this->deliverable->reviewed_at?->toISOString(),
            'message' => $isApproved
                ? __(':reviewer approved your deliverable ":name" 🎉', [
                    'reviewer' => $this->reviewerName,
                    'name' => $this->deliverable->name,
                ])
                : __(':reviewer rejected your deliverable ":name"', [
                    'reviewer' => $this->reviewerName,
                    'name' => $this->deliverable->name,
                ]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
