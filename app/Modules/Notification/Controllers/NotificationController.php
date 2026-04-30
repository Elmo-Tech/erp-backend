<?php

namespace App\Modules\Notification\Controllers;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $notifications = $user->notifications()->paginate($request->get('perPage', 15));
        
        return ApiResponse::success([
            'notifications' => $notifications->items(),
            'pagination' => [
                'total' => $notifications->total(),
                'perPage' => $notifications->perPage(),
                'currentPage' => $notifications->currentPage(),
                'totalPages' => $notifications->lastPage()
            ]
        ], 'Notifications retrieved successfully');
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->find($id);
        
        if ($notification) {
            $notification->markAsRead();
        }
        
        return ApiResponse::success(null, 'Notification marked as read');
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();
        
        return ApiResponse::success(null, 'All notifications marked as read');
    }
}
