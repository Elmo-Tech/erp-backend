<?php

namespace App\Modules\Notification\Notifications;

use App\Modules\Lead\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeadFollowUpReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Lead $lead,
        public bool $isOverdue = false
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->isOverdue
            ? __('Overdue Lead Follow-up')
            : __('Lead Follow-up Reminder');

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]));

        if ($this->isOverdue) {
            $message->line(__('You have an overdue follow-up for lead ":lead".', [
                'lead' => $this->lead->name,
            ]));
        } else {
            $message->line(__('Reminder: Follow up with lead ":lead" is scheduled for today.', [
                'lead' => $this->lead->name,
            ]));
        }

        return $message
            ->line(__('Company: :company', ['company' => $this->lead->company ?? 'N/A']))
            ->line(__('Status: :status', ['status' => $this->lead->status->value]))
            ->line(__('Priority: :priority', ['priority' => $this->lead->priority->value]))
            ->action(__('View Lead'), url("/leads/{$this->lead->id}"))
            ->line(__('Don\'t miss this opportunity!'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'lead_follow_up_reminder',
            'lead_id' => $this->lead->id,
            'lead_name' => $this->lead->name,
            'company' => $this->lead->company,
            'status' => $this->lead->status->value,
            'priority' => $this->lead->priority->value,
            'next_follow_up_at' => $this->lead->next_follow_up_at?->toDateTimeString(),
            'is_overdue' => $this->isOverdue,
            'message' => $this->isOverdue
                ? __('Overdue follow-up for lead: :name', ['name' => $this->lead->name])
                : __('Follow-up reminder for lead: :name', ['name' => $this->lead->name]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
