<?php

namespace App\Modules\Notification\Notifications;

use App\Modules\Proposal\Models\Proposal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Proposal $proposal,
        private int $daysUntilExpiry
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expiryText = $this->daysUntilExpiry === 0
            ? __('today')
            : __('in :days days', ['days' => $this->daysUntilExpiry]);

        return (new MailMessage)
            ->subject(__('proposals.expiring_notification_subject'))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('proposals.expiring_notification_line', [
                'title' => $this->proposal->title,
                'lead' => $this->proposal->lead?->name,
                'expiry' => $expiryText,
            ]))
            ->action(__('proposals.view_action'), url("/proposals/{$this->proposal->id}"))
            ->line(__('proposals.expiring_notification_footer'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'proposal_expiring',
            'proposal_id' => $this->proposal->id,
            'proposal_title' => $this->proposal->title,
            'lead_id' => $this->proposal->lead_id,
            'lead_name' => $this->proposal->lead?->name,
            'expiry_date' => $this->proposal->expiry_date?->toDateString(),
            'days_until_expiry' => $this->daysUntilExpiry,
            'message' => __('proposals.expiring_notification_message', [
                'title' => $this->proposal->title,
                'days' => $this->daysUntilExpiry,
            ]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
