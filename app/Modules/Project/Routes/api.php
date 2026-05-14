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

Route::prefix('v1')->middleware(['auth:sanctum', 'locale'])->group(function () {

    // ── Project read routes ───────────────────────────────────────────────────
    // Requires view_projects OR manage_all_projects (the pipe is Spatie's OR).
    Route::middleware('permission:view_projects|manage_all_projects')->group(function () {

        Route::get('projects/pinned', [ProjectController::class, 'pinned'])
            ->name('projects.pinned');

        Route::get('projects', [ProjectController::class, 'index'])
            ->name('projects.index');

        Route::get('projects/tasks/my-active', [ProjectTaskController::class, 'myActive'])
            ->name('projects.tasks.my-active');

        Route::get('projects/time-entries/active', [ProjectTimeEntryController::class, 'active'])
            ->name('projects.time-entries.active');

        Route::get('projects/{project}', [ProjectController::class, 'show'])
            ->name('projects.show');

        Route::post('projects/{project}/pin', [ProjectController::class, 'pin'])
            ->name('projects.pin');

        Route::delete('projects/{project}/pin', [ProjectController::class, 'unpin'])
            ->name('projects.unpin');

        Route::get('projects/{project}/history', [ProjectHistoryController::class, 'index'])
            ->name('projects.history');

        // ── Project files (read) ──────────────────────────────────────────────
        Route::get('projects/{project}/files', [ProjectFileController::class, 'index'])
            ->name('projects.files.index');

        Route::get('projects/{project}/files/{file}', [ProjectFileController::class, 'show'])
            ->name('projects.files.show');

        // ── Project members (read) ────────────────────────────────────────────
        Route::get('projects/{project}/members', [ProjectMemberController::class, 'index'])
            ->name('projects.members.index');

        // ── Wikis (read) ──────────────────────────────────────────────────────
        Route::get('projects/{project}/wikis', [ProjectWikiController::class, 'index'])
            ->name('projects.wikis.index');

        Route::get('projects/{project}/wikis/{wiki}', [ProjectWikiController::class, 'show'])
            ->name('projects.wikis.show');

        // ── Milestones (read) ─────────────────────────────────────────────────
        Route::get('projects/{project}/milestones', [MilestoneController::class, 'index'])
            ->name('projects.milestones.index');

        Route::get('projects/{project}/milestones/{milestone}', [MilestoneController::class, 'show'])
            ->name('projects.milestones.show');

        // ── Tasks (read) ──────────────────────────────────────────────────────
        Route::get('projects/{project}/tasks', [ProjectTaskController::class, 'index'])
            ->name('projects.tasks.index');

        Route::get('projects/{project}/tasks/{task}', [ProjectTaskController::class, 'show'])
            ->name('projects.tasks.show');

        // ── Time entries (read) ───────────────────────────────────────────────
        Route::get(
            'projects/{project}/tasks/{task}/time-entries',
            [ProjectTimeEntryController::class, 'index']
        )->name('projects.tasks.time-entries.index');

        // ── Change requests (read) ────────────────────────────────────────────
        Route::get('projects/{project}/change-requests', [ChangeRequestController::class, 'index'])
            ->name('projects.change-requests.index');

        Route::get('projects/{project}/change-requests/{changeRequest}', [ChangeRequestController::class, 'show'])
            ->name('projects.change-requests.show');
    });

    // ── Project write routes ──────────────────────────────────────────────────
    Route::middleware('permission:create_projects|manage_all_projects')->group(function () {
        Route::post('projects/convert', [ProjectController::class, 'convert'])
            ->name('projects.convert');

        Route::post('projects/direct', [ProjectController::class, 'storeDirect'])
            ->name('projects.store-direct');

        Route::post('projects', [ProjectController::class, 'store'])
            ->name('projects.store');
    });

    Route::middleware('permission:edit_projects|manage_all_projects')->group(function () {
        Route::put('projects/{project}', [ProjectController::class, 'update'])
            ->name('projects.update');

        Route::patch('projects/{project}', [ProjectController::class, 'update'])
            ->name('projects.patch');

        Route::patch('projects/{project}/status', [ProjectController::class, 'updateStatus'])
            ->name('projects.update-status');
    });

    Route::middleware('permission:delete_projects|manage_all_projects')->group(function () {
        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])
            ->name('projects.destroy');
    });

    // ── UI Approval ───────────────────────────────────────────────────────────
    Route::middleware('permission:approve_project_ui|access_client_portal')->group(function () {
        Route::post('projects/{project}/approve-ui', [ProjectController::class, 'approveUI'])
            ->name('projects.approve-ui');
    });

    // ── Project files (write) ─────────────────────────────────────────────────
    Route::middleware('permission:upload_project_files|manage_project_files')->group(function () {
        Route::post('projects/{project}/files', [ProjectFileController::class, 'store'])
            ->name('projects.files.store');

        Route::put('projects/{project}/files/{file}', [ProjectFileController::class, 'update'])
            ->name('projects.files.update');
    });

    Route::middleware('permission:delete_project_files|manage_project_files')->group(function () {
        Route::delete('projects/{project}/files/{file}', [ProjectFileController::class, 'destroy'])
            ->name('projects.files.destroy');
    });

    // ── Project members (write) ───────────────────────────────────────────────
    Route::middleware('permission:add_project_members|manage_project_members')->group(function () {
        Route::post('projects/{project}/members', [ProjectMemberController::class, 'store'])
            ->name('projects.members.store');

        Route::put('projects/{project}/members/{member}', [ProjectMemberController::class, 'update'])
            ->name('projects.members.update');
    });

    Route::middleware('permission:remove_project_members|manage_project_members')->group(function () {
        Route::delete('projects/{project}/members/{member}', [ProjectMemberController::class, 'destroy'])
            ->name('projects.members.destroy');
    });

    // ── Wikis (write) ─────────────────────────────────────────────────────────
    Route::middleware('permission:create_wiki|manage_wiki')->group(function () {
        Route::post('projects/{project}/wikis', [ProjectWikiController::class, 'store'])
            ->name('projects.wikis.store');
    });

    Route::middleware('permission:edit_wiki|manage_wiki')->group(function () {
        Route::put('projects/{project}/wikis/{wiki}', [ProjectWikiController::class, 'update'])
            ->name('projects.wikis.update');

        Route::patch('projects/{project}/wikis/{wiki}', [ProjectWikiController::class, 'update'])
            ->name('projects.wikis.patch');
    });

    Route::middleware('permission:delete_wiki|manage_wiki')->group(function () {
        Route::delete('projects/{project}/wikis/{wiki}', [ProjectWikiController::class, 'destroy'])
            ->name('projects.wikis.destroy');
    });

    // ── Milestones (write) ────────────────────────────────────────────────────
    Route::middleware('permission:manage_projects|manage_all_projects')->group(function () {
        Route::post('projects/{project}/milestones', [MilestoneController::class, 'store'])
            ->name('projects.milestones.store');

        Route::put('projects/{project}/milestones/{milestone}', [MilestoneController::class, 'update'])
            ->name('projects.milestones.update');

        Route::patch('projects/{project}/milestones/{milestone}', [MilestoneController::class, 'update'])
            ->name('projects.milestones.patch');

        Route::delete('projects/{project}/milestones/{milestone}', [MilestoneController::class, 'destroy'])
            ->name('projects.milestones.destroy');
    });

    // ── Tasks (write) ─────────────────────────────────────────────────────────
    Route::middleware('permission:create_tasks|manage_tasks|manage_all_projects')->group(function () {
        Route::post('projects/{project}/tasks', [ProjectTaskController::class, 'store'])
            ->name('projects.tasks.store');
    });

    Route::middleware('permission:edit_tasks|manage_tasks|work_on_tasks|manage_all_projects')->group(function () {
        Route::put('projects/{project}/tasks/{task}', [ProjectTaskController::class, 'update'])
            ->name('projects.tasks.update');

        Route::patch('projects/{project}/tasks/{task}', [ProjectTaskController::class, 'update'])
            ->name('projects.tasks.patch');
    });

    Route::middleware('permission:delete_tasks|manage_tasks|manage_all_projects')->group(function () {
        Route::delete('projects/{project}/tasks/{task}', [ProjectTaskController::class, 'destroy'])
            ->name('projects.tasks.destroy');
    });

    Route::middleware('permission:edit_tasks|manage_tasks|manage_all_projects')->group(function () {
        Route::post('projects/{project}/tasks/{task}/complete', [ProjectTaskController::class, 'complete'])
            ->name('projects.tasks.complete');
    });

    // ── Task Deliverables ─────────────────────────────────────────────────────
    Route::middleware('permission:edit_tasks|manage_tasks|manage_deliverables|delete_project_task_deliverables|manage_all_projects')->group(function () {
        Route::post(
            'projects/{project}/tasks/{task}/deliverables',
            [ProjectTaskDeliverableController::class, 'store']
        )->name('projects.tasks.deliverables.store');
    });

    Route::middleware('permission:work_on_tasks|edit_tasks|manage_tasks|manage_all_projects')->group(function () {
        Route::patch(
            'projects/{project}/tasks/{task}/deliverables/{deliverable}/toggle',
            [ProjectTaskDeliverableController::class, 'toggle']
        )->name('projects.tasks.deliverables.toggle');
    });

    Route::middleware('permission:edit_tasks|manage_tasks|manage_deliverables|manage_all_projects')->group(function () {
        Route::put(
            'projects/{project}/tasks/{task}/deliverables/{deliverable}',
            [ProjectTaskDeliverableController::class, 'update']
        )->name('projects.tasks.deliverables.update');

        Route::patch(
            'projects/{project}/tasks/{task}/deliverables/{deliverable}',
            [ProjectTaskDeliverableController::class, 'update']
        )->name('projects.tasks.deliverables.patch');

        Route::delete(
            'projects/{project}/tasks/{task}/deliverables/{deliverable}',
            [ProjectTaskDeliverableController::class, 'destroy']
        )->name('projects.tasks.deliverables.destroy');
    });

    // ── Time Entries (write) ──────────────────────────────────────────────────
    Route::middleware('permission:start_timer|log_time|manage_time_entries')->group(function () {
        Route::post(
            'projects/{project}/tasks/{task}/time-entries/toggle',
            [ProjectTimeEntryController::class, 'toggle']
        )->name('projects.tasks.time-entries.toggle');

        Route::post(
            'projects/{project}/tasks/{task}/time-entries',
            [ProjectTimeEntryController::class, 'store']
        )->name('projects.tasks.time-entries.store');
    });

    Route::middleware('permission:log_time|edit_tasks|manage_tasks|manage_time_entries|delete_time_entries|manage_all_projects')->group(function () {
        Route::put(
            'projects/{project}/tasks/{task}/time-entries/{entry}',
            [ProjectTimeEntryController::class, 'update']
        )->name('projects.tasks.time-entries.update');

        Route::patch(
            'projects/{project}/tasks/{task}/time-entries/{entry}',
            [ProjectTimeEntryController::class, 'update']
        )->name('projects.tasks.time-entries.patch');

        Route::delete(
            'projects/{project}/tasks/{task}/time-entries/{entry}',
            [ProjectTimeEntryController::class, 'destroy']
        )->name('projects.tasks.time-entries.destroy');
    });

    // ── Change Requests (write) ───────────────────────────────────────────────
    Route::middleware('permission:manage_change_requests')->group(function () {
        Route::post('projects/{project}/change-requests', [ChangeRequestController::class, 'store'])
            ->name('projects.change-requests.store');

        Route::put('projects/{project}/change-requests/{changeRequest}', [ChangeRequestController::class, 'update'])
            ->name('projects.change-requests.update');

        Route::patch('projects/{project}/change-requests/{changeRequest}', [ChangeRequestController::class, 'update'])
            ->name('projects.change-requests.patch');

        Route::delete('projects/{project}/change-requests/{changeRequest}', [ChangeRequestController::class, 'destroy'])
            ->name('projects.change-requests.destroy');
    });
});
