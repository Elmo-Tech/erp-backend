<?php

namespace App\Modules\Notification\Actions;

use App\Modules\User\Models\User;

class MarkNotificationAsReadAction
{
    public function execute(User $user, string $notificationId): bool
    {
        $notification = $user->notifications()->find($notificationId);

        if (! $notification) {
            return false;
        }

        $notification->markAsRead();

        return true;
    }
}
