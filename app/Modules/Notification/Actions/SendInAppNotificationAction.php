<?php

namespace App\Modules\Notification\Actions;

use App\Modules\User\Models\User;
use App\Modules\Notification\Events\NotificationCreated;
use Illuminate\Support\Facades\Notification;

class SendInAppNotificationAction
{
    public function execute(User $user, string $notificationClass, ...$constructorArgs): void
    {
        $user->notify(new $notificationClass(...$constructorArgs));

        $latestNotification = $user->notifications()->latest()->first();

        if ($latestNotification) {
            event(new NotificationCreated($user->id, $latestNotification->data));
        }
    }

    public function executeForUsers(iterable $users, string $notificationClass, ...$constructorArgs): void
    {
        Notification::send($users, new $notificationClass(...$constructorArgs));

        foreach ($users as $user) {
            $latestNotification = $user->notifications()->latest()->first();

            if ($latestNotification) {
                event(new NotificationCreated($user->id, $latestNotification->data));
            }
        }
    }
}
