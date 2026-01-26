<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserRoleChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $oldRole,
        public string $newRole,
        public string $changedByName,
        public ?int $changedById = null,
        public ?array $addedPermissions = [],
        public ?array $removedPermissions = []
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('Your Role Has Been Updated'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':changer has updated your role in the system.', [
                'changer' => $this->changedByName,
            ]))
            ->line('**' . __('Role Change:') . '**')
            ->line(__('Previous Role: :old', ['old' => ucfirst($this->oldRole)]))
            ->line(__('New Role: :new', ['new' => ucfirst($this->newRole)]));

        if (!empty($this->addedPermissions)) {
            $message->line('**' . __('New Permissions Added:') . '**');
            foreach (array_slice($this->addedPermissions, 0, 5) as $permission) {
                $message->line('✓ ' . ucfirst(str_replace('_', ' ', $permission)));
            }
            if (count($this->addedPermissions) > 5) {
                $message->line(__('... and :count more', ['count' => count($this->addedPermissions) - 5]));
            }
        }

        if (!empty($this->removedPermissions)) {
            $message->line('**' . __('Permissions Removed:') . '**');
            foreach (array_slice($this->removedPermissions, 0, 5) as $permission) {
                $message->line('✗ ' . ucfirst(str_replace('_', ' ', $permission)));
            }
            if (count($this->removedPermissions) > 5) {
                $message->line(__('... and :count more', ['count' => count($this->removedPermissions) - 5]));
            }
        }

        return $message
            ->action(__('View Your Profile'), url('/dashboard/profile'))
            ->line(__('Please review your new permissions and contact your administrator if you have any questions.'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'user_role_changed',
            'old_role' => $this->oldRole,
            'new_role' => $this->newRole,
            'old_role_label' => ucfirst($this->oldRole),
            'new_role_label' => ucfirst($this->newRole),
            'changed_by_id' => $this->changedById,
            'changed_by_name' => $this->changedByName,
            'added_permissions' => $this->addedPermissions,
            'removed_permissions' => $this->removedPermissions,
            'changed_at' => now()->toISOString(),
            'message' => __(':changer changed your role from :old to :new', [
                'changer' => $this->changedByName,
                'old' => ucfirst($this->oldRole),
                'new' => ucfirst($this->newRole),
            ]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
