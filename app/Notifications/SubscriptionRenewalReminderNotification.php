<?php

namespace App\Notifications;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRenewalReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The contract instance.
     */
    public Contract $contract;

    /**
     * Number of days until expiration.
     */
    public int $daysRemaining;

    /**
     * The type of reminder (30, 7, or 1 day).
     */
    public string $reminderType;

    /**
     * The reference person (sales responsible) - optional.
     */
    public ?User $referencePerson;

    /**
     * Create a new notification instance.
     */
    public function __construct(Contract $contract, int $daysRemaining, string $reminderType, ?User $referencePerson = null)
    {
        $this->contract = $contract;
        $this->daysRemaining = $daysRemaining;
        $this->reminderType = $reminderType;
        $this->referencePerson = $referencePerson;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = new MailMessage();

        switch ($this->reminderType) {
            case '30':
                return $this->build30DayMail($mail);
            case '7':
                return $this->build7DayMail($mail);
            case '1':
                return $this->build1DayMail($mail);
            default:
                return $this->buildDefaultMail($mail);
        }
    }

    /**
     * Build 30-day reminder email.
     */
    protected function build30DayMail(MailMessage $mail): MailMessage
    {
        $clientName = $this->contract->party_two['name'] ?? 'Valued Client';
        $contractNumber = $this->contract->contract_number;
        $endDate = \Carbon\Carbon::parse($this->contract->end_date)->format('F j, Y');

        return $mail
            ->subject("Your Subscription Expires in 30 Days - Action Required")
            ->greeting("Dear {$clientName},")
            ->line("This is a friendly reminder that your subscription/service contract will expire in **30 days** on **{$endDate}**.")
            ->line("Contract Number: {$contractNumber}")
            ->line("To ensure uninterrupted service, we recommend renewing your subscription at your earliest convenience.")
            ->action('Renew Now', route('client-portal.renewal', ['contract' => $this->contract->id]))
            ->line("If you have any questions or need assistance with the renewal process, please don't hesitate to contact us.")
            ->line("Thank you for your continued partnership.");
    }

    /**
     * Build 7-day reminder email.
     */
    protected function build7DayMail(MailMessage $mail): MailMessage
    {
        $clientName = $this->contract->party_two['name'] ?? 'Valued Client';
        $contractNumber = $this->contract->contract_number;
        $endDate = \Carbon\Carbon::parse($this->contract->end_date)->format('F j, Y');

        return $mail
            ->subject("URGENT: Your Subscription Expires in 7 Days")
            ->greeting("Dear {$clientName},")
            ->line("**This is an important reminder** that your subscription will expire in **7 days** on **{$endDate}**.")
            ->line("Contract Number: {$contractNumber}")
            ->line("Please renew your subscription before the expiration date to avoid any service interruption.")
            ->action('Renew Now', route('client-portal.renewal', ['contract' => $this->contract->id]))
            ->line("If you need an extension or have any questions, please contact us immediately.");
    }

    /**
     * Build 1-day reminder email.
     */
    protected function build1DayMail(MailMessage $mail): MailMessage
    {
        $clientName = $this->contract->party_two['name'] ?? 'Valued Client';
        $contractNumber = $this->contract->contract_number;
        $endDate = \Carbon\Carbon::parse($this->contract->end_date)->format('F j, Y');

        return $mail
            ->subject("CRITICAL: Your Subscription Expires Tomorrow!")
            ->greeting("Dear {$clientName},")
            ->line("**Your subscription expires tomorrow** ({$endDate})!")
            ->line("Contract Number: {$contractNumber}")
            ->line("This is your final reminder. Please renew immediately to avoid service suspension.")
            ->action('Renew Now', route('client-portal.renewal', ['contract' => $this->contract->id]))
            ->line("If you've already renewed, please ignore this message.");
    }

    /**
     * Build default/expiry day email.
     */
    protected function buildDefaultMail(MailMessage $mail): MailMessage
    {
        $clientName = $this->contract->party_two['name'] ?? 'Valued Client';
        $contractNumber = $this->contract->contract_number;
        $endDate = \Carbon\Carbon::parse($this->contract->end_date)->format('F j, Y');

        return $mail
            ->subject("Your Subscription Has Expired")
            ->greeting("Dear {$clientName},")
            ->line("Your subscription has expired as of {$endDate}.")
            ->line("Contract Number: {$contractNumber}")
            ->line("Please contact us to discuss renewal options.")
            ->action('Contact Us', route('contact'));
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'contract_id' => $this->contract->id,
            'contract_number' => $this->contract->contract_number,
            'days_remaining' => $this->daysRemaining,
            'reminder_type' => $this->reminderType,
            'end_date' => $this->contract->end_date,
            'message' => "Subscription renewal reminder: {$this->daysRemaining} days remaining",
        ];
    }
}
