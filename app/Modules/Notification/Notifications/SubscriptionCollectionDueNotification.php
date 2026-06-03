<?php

namespace App\Modules\Notification\Notifications;

use App\Modules\Finance\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionCollectionDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Subscription $subscription
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $this->subscription->loadMissing(['client', 'project']);

        return (new MailMessage)
            ->subject('Subscription payment due: '.$this->subscription->name)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A subscription collection is due soon.')
            ->line('Subscription: '.$this->subscription->name)
            ->line('Client: '.($this->subscription->client?->display_name ?? $this->subscription->client?->company_name))
            ->line('Due date: '.$this->subscription->next_billing_date?->toDateString())
            ->line('Amount: '.number_format((float) $this->subscription->cost, 2).' '.$this->subscription->currency)
            ->action('Open Subscriptions', $this->frontendUrl('/dashboard/finance/subscriptions'));
    }

    public function toArray($notifiable): array
    {
        $this->subscription->loadMissing(['client', 'project']);

        return [
            'type' => 'subscription_collection_due',
            'title' => 'Subscription payment due',
            'message' => $this->subscription->name.' is due on '.$this->subscription->next_billing_date?->toDateString(),
            'subscription_id' => $this->subscription->id,
            'client_id' => $this->subscription->client_id,
            'client_name' => $this->subscription->client?->display_name ?? $this->subscription->client?->company_name,
            'amount' => (float) $this->subscription->cost,
            'currency' => $this->subscription->currency,
            'due_date' => $this->subscription->next_billing_date?->toDateString(),
            'url' => '/dashboard/finance/subscriptions',
        ];
    }

    private function frontendUrl(string $path): string
    {
        return rtrim((string) config('app.frontend_url', config('app.url')), '/').$path;
    }
}
