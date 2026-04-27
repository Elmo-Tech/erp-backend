<?php

namespace App\Modules\Notification\Actions;

use App\Modules\User\Models\User;

class GetUserNotificationsAction
{
    public function execute(User $user, int $perPage = 20): array
    {
        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return [
            'notifications' => $notifications->map(fn ($n) => [
                'notificationId' => $n->id,
                'type' => $n->data['type'] ?? 'general',
                'message' => $n->data['message'] ?? '',
                'data' => $n->data,
                'readAt' => $n->read_at?->toISOString(),
                'createdAt' => $n->created_at->toISOString(),
            ]),
            'unreadCount' => $user->unreadNotifications()->count(),
            'pagination' => [
                'total' => $notifications->total(),
                'perPage' => $notifications->perPage(),
                'currentPage' => $notifications->currentPage(),
                'totalPages' => $notifications->lastPage(),
            ],
        ];
    }

    public function getUnreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }
}
