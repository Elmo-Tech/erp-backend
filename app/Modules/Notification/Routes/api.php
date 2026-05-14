<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Notification\Controllers\NotificationController;

Route::prefix('v1/notifications')->middleware(['auth:sanctum', 'locale'])->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::patch('/read-all', [NotificationController::class, 'markAllAsRead']);
});
