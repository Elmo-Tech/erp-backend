<?php

namespace App\Modules\Notification\Notifications;

use App\Modules\Lead\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeadAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Lead $lead,
        public string $assignerName,
        public ?int $assignerId = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $priority = ucfirst($this->lead->priority?->value ?? 'medium');
        $temperature = ucfirst($this->lead->temperature?->value ?? 'warm');
        $source = ucfirst($this->lead->source?->value ?? 'unknown');

        return (new MailMessage)
            ->subject(__('New Lead Assigned: :company', ['company' => $this->lead->company]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':assigner has assigned you a new lead.', ['assigner' => $this->assignerName]))
            ->line('**'.__('Lead Details:').'**')
            ->line(__('Company: :company', ['company' => $this->lead->company]))
            ->line(__('Contact: :contact', ['contact' => $this->lead->name]))
            ->line(__('Email: :email', ['email' => $this->lead->email]))
            ->line(__('Phone: :phone', ['phone' => $this->lead->phone ?? __('Not provided')]))
            ->line(__('Priority: :priority', ['priority' => $priority]))
            ->line(__('Temperature: :temperature', ['temperature' => $temperature]))
            ->line(__('Source: :source', ['source' => $source]))
            ->when($this->lead->notes, function ($message) {
                return $message->line(__('Notes: :notes', [
                    'notes' => substr($this->lead->notes, 0, 150).'...',
                ]));
            })
            ->action(__('View Lead'), url("/dashboard/sales/leads/{$this->lead->id}"))
            ->line(__('Please follow up with this lead as soon as possible.'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'lead_assigned',
            'lead_id' => $this->lead->id,
            'lead_company' => $this->lead->company,
            'lead_name' => $this->lead->name,
            'lead_email' => $this->lead->email,
            'lead_priority' => $this->lead->priority?->value,
            'lead_temperature' => $this->lead->temperature?->value,
            'lead_source' => $this->lead->source?->value,
            'assigner_id' => $this->assignerId,
            'assigner_name' => $this->assignerName,
            'assigned_at' => now()->toISOString(),
            'message' => __(':assigner assigned you lead: :company', [
                'assigner' => $this->assignerName,
                'company' => $this->lead->company,
            ]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
