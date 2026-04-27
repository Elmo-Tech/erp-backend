<?php

namespace App\Modules\Notification\Actions;

use App\Modules\User\Models\User;

class MarkAllNotificationsAsReadAction
{
    public function execute(User $user): void
    {
        $user->unreadNotifications->markAsRead();
    }
}
