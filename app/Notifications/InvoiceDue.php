<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceDue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Invoice $invoice,
        public int $daysUntilDue
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dueText = $this->daysUntilDue === 0 
            ? __('today') 
            : __(':days days', ['days' => $this->daysUntilDue]);

        return (new MailMessage)
            ->subject(__('Invoice Due Reminder'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Invoice #:number for :project is due :due.', [
                'number' => $this->invoice->invoice_number,
                'project' => $this->invoice->project->title,
                'due' => $dueText,
            ]))
            ->line(__('Amount: :amount :currency', [
                'amount' => number_format($this->invoice->total_amount, 2),
                'currency' => $this->invoice->currency,
            ]))
            ->action(__('View Invoice'), url("/invoices/{$this->invoice->id}"))
            ->line(__('Please ensure timely payment to avoid any service interruptions.'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'invoice_due',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'project_id' => $this->invoice->project_id,
            'project_title' => $this->invoice->project->title,
            'amount' => $this->invoice->total_amount,
            'currency' => $this->invoice->currency,
            'due_date' => $this->invoice->due_date->toDateString(),
            'days_until_due' => $this->daysUntilDue,
            'message' => __('Invoice #:number is due in :days days', [
                'number' => $this->invoice->invoice_number,
                'days' => $this->daysUntilDue,
            ]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
