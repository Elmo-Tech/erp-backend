<?php

namespace App\Modules\Notification\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Notification\Actions\GetUserNotificationsAction;
use App\Modules\Notification\Actions\MarkNotificationAsReadAction;
use App\Modules\Notification\Actions\MarkAllNotificationsAsReadAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    public function index(Request $request, GetUserNotificationsAction $action): JsonResponse
    {
        $result = $action->execute($request->user(), $request->input('perPage', 20));

        return ApiResponse::success($result);
    }

    public function unreadCount(Request $request, GetUserNotificationsAction $action): JsonResponse
    {
        $count = $action->getUnreadCount($request->user());

        return ApiResponse::success(['unreadCount' => $count]);
    }

    public function markAsRead(Request $request, string $id, MarkNotificationAsReadAction $action): JsonResponse
    {
        $marked = $action->execute($request->user(), $id);

        if (! $marked) {
            return ApiResponse::error('Notification not found', 404);
        }

        return ApiResponse::success(null, 'Notification marked as read');
    }

    public function markAllAsRead(Request $request, MarkAllNotificationsAsReadAction $action): JsonResponse
    {
        $action->execute($request->user());

        return ApiResponse::success(null, 'All notifications marked as read');
    }
}
