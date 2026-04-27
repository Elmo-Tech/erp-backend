<?php

use App\Modules\Auth\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('auth.login');

    // Registration remains disabled by default. Keep the controller action for controlled enablement.
    // Route::post('register', [AuthController::class, 'register'])
    //     ->middleware('throttle:3,1')
    //     ->name('auth.register');

    Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('profile', [AuthController::class, 'profile'])->name('auth.profile');
    Route::put('profile', [AuthController::class, 'updateProfile'])->name('auth.profile.update');
    Route::post('profile/avatar', [AuthController::class, 'updateAvatar'])->name('auth.profile.avatar');
    Route::post('change-password', [AuthController::class, 'changePassword'])
        ->middleware('auth:sanctum')
        ->name('auth.change-password');
});
