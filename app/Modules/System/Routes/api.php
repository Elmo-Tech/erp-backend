<?php

use App\Modules\System\Controllers\EnumController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/enums')->group(function () {
    Route::get('user-specializations', [EnumController::class, 'getUserSpecializations'])->name('enums.user-specializations');
    Route::get('task-categories', [EnumController::class, 'getTaskCategories'])->name('enums.task-categories');
    Route::get('compatible-task-categories/{specialization}', [EnumController::class, 'getCompatibleTaskCategories'])->name('enums.compatible-task-categories');
});
