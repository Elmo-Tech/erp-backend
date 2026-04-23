<?php

use App\Modules\Project\Controllers\ProjectController;
use App\Modules\Project\Controllers\ProjectFileController;
use App\Modules\Project\Controllers\ProjectHistoryController;
use App\Modules\Project\Controllers\ProjectMemberController;
use App\Modules\Project\Controllers\ProjectWikiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('projects/pinned', [ProjectController::class, 'pinned'])
        ->name('projects.pinned');
    Route::post('projects/convert', [ProjectController::class, 'convert'])
        ->name('projects.convert');
    Route::post('projects/direct', [ProjectController::class, 'storeDirect'])
        ->name('projects.store-direct');

    Route::apiResource('projects', ProjectController::class);

    Route::post('projects/{project}/pin', [ProjectController::class, 'pin'])
        ->name('projects.pin');
    Route::delete('projects/{project}/pin', [ProjectController::class, 'unpin'])
        ->name('projects.unpin');

    Route::patch('projects/{project}/status', [ProjectController::class, 'updateStatus'])
        ->name('projects.update-status');
    Route::post('projects/{project}/approve-ui', [ProjectController::class, 'approveUI'])
        ->name('projects.approve-ui');

    Route::get('projects/{project}/history', [ProjectHistoryController::class, 'index'])
        ->name('projects.history');

    Route::apiResource('projects.wikis', ProjectWikiController::class);
    Route::apiResource('projects.files', ProjectFileController::class);
    Route::apiResource('projects.members', ProjectMemberController::class)->except(['show']);
});
