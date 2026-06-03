<?php

namespace App\Modules\Notification\Notifications;

use App\Modules\Client\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientFollowUpReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Client $client
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Client follow-up due: '.$this->client->display_name)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A client follow-up is due soon.')
            ->line('Client: '.$this->client->display_name)
            ->line('Expected close date: '.$this->client->expected_close_date?->toDateString())
            ->action('Open Client', $this->frontendUrl('/dashboard/clients/'.$this->client->id));
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'client_followup_due',
            'title' => 'Client follow-up due',
            'message' => $this->client->display_name,
            'client_id' => $this->client->id,
            'expected_close_date' => $this->client->expected_close_date?->toDateString(),
            'url' => '/dashboard/clients/'.$this->client->id,
        ];
    }

    private function frontendUrl(string $path): string
    {
        return rtrim((string) config('app.frontend_url', config('app.url')), '/').$path;
    }
}
