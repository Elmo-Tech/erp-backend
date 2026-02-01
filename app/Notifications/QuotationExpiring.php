<?php

namespace App\Notifications;

use App\Models\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuotationExpiring extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Quotation $quotation
    ) {
        $this->daysUntilExpiry = $quotation->expires_at ? 
            now()->diffInDays($quotation->expires_at, false) : 0;
    }

    public int $daysUntilExpiry;

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
            ->subject(__('Quotation Expiring Soon'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Quotation #:number for lead ":lead" expires :expiry.', [
                'number' => $this->quotation->quotation_number ?? $this->quotation->id,
                'lead' => $this->quotation->lead->name,
                'expiry' => $expiryText,
            ]))
            ->line(__('Total Value: :amount :currency', [
                'amount' => number_format($this->quotation->final_price, 2),
                'currency' => $this->quotation->currency,
            ]))
            ->action(__('View Quotation'), url("/quotations/{$this->quotation->id}"))
            ->line(__('Please follow up with the client before the quotation expires.'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'quotation_expiring',
            'quotation_id' => $this->quotation->id,
            'quotation_number' => $this->quotation->quotation_number ?? $this->quotation->id,
            'lead_id' => $this->quotation->lead_id,
            'lead_name' => $this->quotation->lead->name,
            'final_price' => $this->quotation->final_price,
            'currency' => $this->quotation->currency,
            'valid_until' => $this->quotation->expires_at?->toDateString(),
            'days_until_expiry' => $this->daysUntilExpiry,
            'message' => __('Quotation #:number expires in :days days', [
                'number' => $this->quotation->quotation_number ?? $this->quotation->id,
                'days' => $this->daysUntilExpiry,
            ]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
