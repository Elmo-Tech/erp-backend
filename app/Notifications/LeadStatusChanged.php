<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeadStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Lead $lead,
        public string $oldStatus,
        public string $newStatus,
        public string $changedByName,
        public ?int $changedById = null,
        public ?string $reason = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Lead Status Updated: :company', ['company' => $this->lead->company]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':changer has updated the status of lead ":company".', [
                'changer' => $this->changedByName,
                'company' => $this->lead->company,
            ]))
            ->line('**' . __('Status Change:') . '**')
            ->line(__('Previous Status: :old', ['old' => __(ucfirst(str_replace('_', ' ', $this->oldStatus)))]))
            ->line(__('New Status: :new', ['new' => __(ucfirst(str_replace('_', ' ', $this->newStatus)))]))
            ->line('**' . __('Lead Details:') . '**')
            ->line(__('Company: :company', ['company' => $this->lead->company]))
            ->line(__('Contact: :contact', ['contact' => $this->lead->name]))
            ->when($this->reason, function ($message) {
                return $message->line(__('Reason: :reason', ['reason' => $this->reason]));
            })
            ->action(__('View Lead'), url("/dashboard/sales/leads/{$this->lead->id}"))
            ->line(__('Please check the lead for any required actions.'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'lead_status_changed',
            'lead_id' => $this->lead->id,
            'lead_company' => $this->lead->company,
            'lead_name' => $this->lead->name,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'old_status_label' => __(ucfirst(str_replace('_', ' ', $this->oldStatus))),
            'new_status_label' => __(ucfirst(str_replace('_', ' ', $this->newStatus))),
            'changed_by_id' => $this->changedById,
            'changed_by_name' => $this->changedByName,
            'reason' => $this->reason,
            'changed_at' => now()->toISOString(),
            'message' => __(':changer changed lead ":company" status from :old to :new', [
                'changer' => $this->changedByName,
                'company' => $this->lead->company,
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
