<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExpiryWarningNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Model $expirable,
        private string $type, // proposal, quotation
        private int $daysRemaining
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = $this->getTitle();
        $url = $this->getUrl();

        return (new MailMessage)
            ->subject(__('expiry.warning_subject', ['type' => $this->type, 'days' => $this->daysRemaining]))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('expiry.warning_line', [
                'type' => $this->type,
                'title' => $title,
                'days' => $this->daysRemaining,
            ]))
            ->action(__('expiry.view_action'), $url)
            ->line(__('expiry.take_action'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'expiry_warning',
            'expirable_type' => get_class($this->expirable),
            'expirable_id' => $this->expirable->id,
            'item_type' => $this->type,
            'title' => $this->getTitle(),
            'days_remaining' => $this->daysRemaining,
            'expiry_date' => $this->expirable->expiry_date?->toISOString(),
        ];
    }

    private function getTitle(): string
    {
        return match($this->type) {
            'proposal' => $this->expirable->title ?? $this->expirable->proposal_number,
            'quotation' => $this->expirable->quotation_number ?? 'Quotation #' . $this->expirable->id,
            default => 'Item #' . $this->expirable->id,
        };
    }

    private function getUrl(): string
    {
        return match($this->type) {
            'proposal' => url('/proposals/' . $this->expirable->id),
            'quotation' => url('/quotations/' . $this->expirable->id),
            default => url('/'),
        };
    }
}
