<?php

namespace App\Notifications;

use App\Models\ChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChangeRequestSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ChangeRequest $changeRequest,
        public string $actorName,
        public ?int $actorId = null,
        public string $event = 'submitted'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Change Request: :title', ['title' => $this->changeRequest->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name ?? __('there')]))
            ->line(__(':actor :event a change request for project ":project".', [
                'actor' => $this->actorName,
                'event' => $this->event,
                'project' => $this->changeRequest->project?->title,
            ]))
            ->line(__('Title: :title', ['title' => $this->changeRequest->title]))
            ->action(__('View Change Request'), url("/dashboard/projects/{$this->changeRequest->project_id}?tab=change-requests&changeRequest={$this->changeRequest->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'change_request_'.$this->event,
            'change_request_id' => $this->changeRequest->id,
            'project_id' => $this->changeRequest->project_id,
            'project_title' => $this->changeRequest->project?->title,
            'actor_id' => $this->actorId,
            'actor_name' => $this->actorName,
            'url' => "/dashboard/projects/{$this->changeRequest->project_id}?tab=change-requests&changeRequest={$this->changeRequest->id}",
            'message' => __(':actor :event change request: :title', [
                'actor' => $this->actorName,
                'event' => $this->event,
                'title' => $this->changeRequest->title,
            ]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
