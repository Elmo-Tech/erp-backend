<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceOverdue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Invoice $invoice,
        public int $daysOverdue
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Invoice Overdue Notice'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Invoice #:number for :project is :days days overdue.', [
                'number' => $this->invoice->invoice_number,
                'project' => $this->invoice->project->title,
                'days' => $this->daysOverdue,
            ]))
            ->line(__('Outstanding Amount: :amount :currency', [
                'amount' => number_format($this->invoice->total_amount, 2),
                'currency' => $this->invoice->currency,
            ]))
            ->action(__('Pay Now'), url("/invoices/{$this->invoice->id}/pay"))
            ->line(__('Please settle this invoice immediately to avoid any service interruptions.'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'invoice_overdue',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'project_id' => $this->invoice->project_id,
            'project_title' => $this->invoice->project->title,
            'amount' => $this->invoice->total_amount,
            'currency' => $this->invoice->currency,
            'due_date' => $this->invoice->due_date->toDateString(),
            'days_overdue' => $this->daysOverdue,
            'message' => __('Invoice #:number is :days days overdue', [
                'number' => $this->invoice->invoice_number,
                'days' => $this->daysOverdue,
            ]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
