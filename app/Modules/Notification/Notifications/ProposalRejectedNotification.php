<?php

namespace App\Modules\Notification\Notifications;

use App\Modules\Proposal\Models\Proposal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Proposal $proposal,
        private ?string $rejectionReason = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('proposals.rejected_notification_subject', ['title' => $this->proposal->title]))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('proposals.rejected_notification_line', [
                'title' => $this->proposal->title,
                'lead' => $this->proposal->lead?->name,
            ]));

        if ($this->rejectionReason) {
            $mail->line(__('proposals.rejection_reason', ['reason' => $this->rejectionReason]));
        }

        return $mail
            ->action(__('proposals.view_action'), url("/proposals/{$this->proposal->id}"))
            ->line(__('proposals.rejected_notification_footer'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'proposal_rejected',
            'proposal_id' => $this->proposal->id,
            'proposal_title' => $this->proposal->title,
            'lead_id' => $this->proposal->lead_id,
            'lead_name' => $this->proposal->lead?->name,
            'rejection_reason' => $this->rejectionReason,
            'rejected_at' => $this->proposal->rejected_at?->toISOString(),
            'message' => __('proposals.rejected_notification_message', [
                'title' => $this->proposal->title,
            ]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
