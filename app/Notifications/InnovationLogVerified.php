<?php

namespace App\Notifications;

use App\Models\InnovationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InnovationLogVerified extends Notification implements ShouldQueue
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
            ->subject(__('Innovation Log Verified! 🎉'))
            ->greeting(__('Congratulations :name!', ['name' => $notifiable->name]))
            ->line(__('Your innovation log ":title" has been verified and approved!', [
                'title' => $this->innovationLog->title,
            ]))
            ->line(__('Category: :category', ['category' => ucfirst(str_replace('_', ' ', $this->innovationLog->category))]))
            ->line(__('Impact Level: :impact', ['impact' => ucfirst($this->innovationLog->impact_level)]))
            ->when($this->innovationLog->points_awarded > 0, function ($message) {
                return $message->line(__('Points Awarded: :points points', [
                    'points' => $this->innovationLog->points_awarded
                ]));
            })
            ->action(__('View Innovation'), url("/dashboard/performance/innovation-logs/{$this->innovationLog->id}"))
            ->line(__('Thank you for contributing to our innovation culture!'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'innovation_log_verified',
            'innovation_log_id' => $this->innovationLog->id,
            'innovation_title' => $this->innovationLog->title,
            'category' => ucfirst(str_replace('_', ' ', $this->innovationLog->category)),
            'impact_level' => ucfirst($this->innovationLog->impact_level),
            'points_awarded' => $this->innovationLog->points_awarded,
            'verified_at' => $this->innovationLog->updated_at->toISOString(),
            'message' => __('Your innovation ":title" has been verified! 🎉', [
                'title' => $this->innovationLog->title,
            ]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}