<?php

namespace App\Modules\Notification\Notifications;

use App\Modules\Finance\Models\FinanceInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FinanceInvoiceDueReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly FinanceInvoice $invoice
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $this->invoice->loadMissing(['client:id,display_name,company_name', 'project:id,title']);

        return (new MailMessage)
            ->subject('Invoice due: '.$this->invoice->invoice_number)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('An invoice needs finance follow-up.')
            ->line('Invoice: '.$this->invoice->invoice_number)
            ->line('Client: '.($this->invoice->client?->display_name ?? $this->invoice->client?->company_name))
            ->line('Due date: '.$this->invoice->due_date?->toDateString())
            ->line('Amount: '.number_format((float) $this->invoice->total_amount, 2))
            ->action('Open Invoices', $this->frontendUrl('/dashboard/finance/invoices'));
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'finance_invoice_due',
            'title' => 'Invoice due',
            'message' => $this->invoice->invoice_number,
            'invoice_id' => $this->invoice->id,
            'client_id' => $this->invoice->client_id,
            'project_id' => $this->invoice->project_id,
            'due_date' => $this->invoice->due_date?->toDateString(),
            'amount' => (float) $this->invoice->total_amount,
            'url' => '/dashboard/finance/invoices',
        ];
    }

    private function frontendUrl(string $path): string
    {
        return rtrim((string) config('app.frontend_url', config('app.url')), '/').$path;
    }
}
