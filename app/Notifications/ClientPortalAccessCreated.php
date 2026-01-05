<?php

namespace App\Notifications;

use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientPortalAccessCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Client $client,
        public string $password
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $portalUrl = config('app.frontend_url') . '/client';

        return (new MailMessage)
            ->subject(__('Welcome to Your Client Portal'))
            ->greeting(__('Hello :name,', ['name' => $this->client->name]))
            ->line(__('Your client portal account has been created. You can now access your projects, invoices, and more.'))
            ->line(__('Here are your login credentials:'))
            ->line(__('Email: :email', ['email' => $this->client->email]))
            ->line(__('Password: :password', ['password' => $this->password]))
            ->action(__('Access Portal'), $portalUrl)
            ->line(__('For security reasons, we recommend changing your password after your first login.'))
            ->line(__('If you have any questions, please contact our support team.'));
    }
}
