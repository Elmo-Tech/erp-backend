<?php

namespace App\Modules\Notification\Notifications;

use App\Models\InnovationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InnovationLogSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public InnovationLog $innovationLog
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New Innovation Log Submitted'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('A new innovation log has been submitted by :user.', [
                'user' => $this->innovationLog->user->name,
            ]))
            ->line(__('Innovation: :title', ['title' => $this->innovationLog->title]))
            ->line(__('Category: :category', ['category' => ucfirst(str_replace('_', ' ', $this->innovationLog->category))]))
            ->line(__('Impact Level: :impact', ['impact' => ucfirst($this->innovationLog->impact_level)]))
            ->when($this->innovationLog->description, function ($message) {
                return $message->line(__('Description: :description', [
                    'description' => substr($this->innovationLog->description, 0, 100).'...',
                ]));
            })
            ->action(__('Review Innovation'), url("/admin/innovation-logs/{$this->innovationLog->id}"))
            ->line(__('Please review and verify this innovation log.'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'innovation_log_submitted',
            'innovation_log_id' => $this->innovationLog->id,
            'innovation_title' => $this->innovationLog->title,
            'user_id' => $this->innovationLog->user_id,
            'user_name' => $this->innovationLog->user->name,
            'category' => ucfirst(str_replace('_', ' ', $this->innovationLog->category)),
            'impact_level' => ucfirst($this->innovationLog->impact_level),
            'submitted_at' => $this->innovationLog->created_at->toISOString(),
            'message' => __(':user submitted a new innovation: :title', [
                'user' => $this->innovationLog->user->name,
                'title' => $this->innovationLog->title,
            ]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
