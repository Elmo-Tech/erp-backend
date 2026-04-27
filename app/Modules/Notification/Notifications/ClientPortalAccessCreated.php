<?php

namespace App\Modules\Notification\Notifications;

use App\Modules\Client\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;

class ClientPortalAccessCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Client $client,
        public ?string $password = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $portalUrl = config('app.frontend_url').'/client';

        // Generate password reset link for secure password setup
        $resetUrl = $this->getPasswordResetUrl($notifiable);

        return (new MailMessage)
            ->subject(__('Welcome to Your Client Portal'))
            ->greeting(__('Hello :name,', ['name' => $this->client->name]))
            ->line(__('Your client portal account has been created. You can now access your projects, invoices, and more.'))
            ->line(__('Your login email: :email', ['email' => $this->client->email]))
            ->line('') // Empty line for spacing
            ->line(__('**For your security, please set up your password using the link below:**'))
            ->action(__('Set Up Your Password'), $resetUrl)
            ->line(__('This link will expire in 60 minutes for security reasons.'))
            ->line(__('After setting up your password, you can access the portal using the button below:'))
            ->action(__('Access Portal'), $portalUrl)
            ->line('') // Empty line for spacing
            ->line(__('If you did not expect this email, please contact our support team immediately.'))
            ->line(__('For any questions, please contact our support team.'));
    }

    /**
     * Generate a password reset URL for the user.
     * This is more secure than sending passwords via email.
     */
    protected function getPasswordResetUrl($notifiable): string
    {
        // Generate a password reset token
        $token = Password::broker()->createToken($notifiable);

        // Build the reset URL
        return config('app.frontend_url').'/reset-password?token='.$token.'&email='.urlencode($notifiable->email);
    }
}
