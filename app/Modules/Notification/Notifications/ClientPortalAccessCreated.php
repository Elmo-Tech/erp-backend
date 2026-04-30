<?php

namespace App\Modules\Notification\Notifications;

use App\Modules\Client\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientPortalAccessCreated extends Notification implements ShouldQueue
{
    use Queueable;

    private Client $client;
    private ?string $password;

    public function __construct(Client $client, ?string $password)
    {
        $this->client = $client;
        $this->password = $password;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Welcome to the Client Portal')
            ->greeting('Hello ' . $this->client->display_name . ',')
            ->line('Your client portal access has been created.');

        if ($this->password) {
            $message->line('Your temporary password is: ' . $this->password)
                ->line('Please change it after logging in for the first time.');
        }

        return $message
            ->action('Go to Portal', config('app.frontend_url') . '/client')
            ->line('Thank you for using our application!');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'portal_access_created',
            'client_id' => $this->client->id,
            'message' => 'Your client portal access has been created.',
        ];
    }
}
