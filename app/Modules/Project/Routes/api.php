<?php

use App\Modules\Project\Controllers\ChangeRequestController;
use App\Modules\Project\Controllers\MilestoneController;
use App\Modules\Project\Controllers\ProjectController;
use App\Modules\Project\Controllers\ProjectFileController;
use App\Modules\Project\Controllers\ProjectHistoryController;
use App\Modules\Project\Controllers\ProjectMemberController;
use App\Modules\Project\Controllers\ProjectTaskController;
use App\Modules\Project\Controllers\ProjectTaskDeliverableController;
use App\Modules\Project\Controllers\ProjectTimeEntryController;
use App\Modules\Project\Controllers\ProjectWikiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // ── Existing project routes (unchanged) ───────────────────────────────────
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

    // ── Project Execution Layer ───────────────────────────────────────────────

    // Milestones
    Route::apiResource('projects.milestones', MilestoneController::class);

    // Execution Tasks
    Route::apiResource('projects.tasks', ProjectTaskController::class)
        ->parameters(['tasks' => 'task']);

    // Complete a task (enforces deliverable check → updates milestone progress)
    Route::post('projects/{project}/tasks/{task}/complete', [ProjectTaskController::class, 'complete'])
        ->name('projects.tasks.complete');

    // Task Deliverables
    Route::post(
        'projects/{project}/tasks/{task}/deliverables',
        [ProjectTaskDeliverableController::class, 'store']
    )->name('projects.tasks.deliverables.store');

    Route::patch(
        'projects/{project}/tasks/{task}/deliverables/{deliverable}/toggle',
        [ProjectTaskDeliverableController::class, 'toggle']
    )->name('projects.tasks.deliverables.toggle');

    Route::delete(
        'projects/{project}/tasks/{task}/deliverables/{deliverable}',
        [ProjectTaskDeliverableController::class, 'destroy']
    )->name('projects.tasks.deliverables.destroy');

    // Time Entries
    Route::get(
        'projects/{project}/tasks/{task}/time-entries',
        [ProjectTimeEntryController::class, 'index']
    )->name('projects.tasks.time-entries.index');

    Route::post(
        'projects/{project}/tasks/{task}/time-entries/toggle',
        [ProjectTimeEntryController::class, 'toggle']
    )->name('projects.tasks.time-entries.toggle');

    Route::post(
        'projects/{project}/tasks/{task}/time-entries',
        [ProjectTimeEntryController::class, 'store']
    )->name('projects.tasks.time-entries.store');

    Route::delete(
        'projects/{project}/tasks/{task}/time-entries/{entry}',
        [ProjectTimeEntryController::class, 'destroy']
    )->name('projects.tasks.time-entries.destroy');

    // Change Requests
    Route::apiResource('projects.change-requests', ChangeRequestController::class)
        ->parameters(['change-requests' => 'changeRequest']);
});
