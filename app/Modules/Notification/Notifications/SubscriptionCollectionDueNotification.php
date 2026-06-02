<?php

namespace App\Modules\Notification\Notifications;

use App\Modules\Finance\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubscriptionCollectionDueNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Subscription $subscription
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
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
}
