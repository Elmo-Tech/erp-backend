<?php

use App\Modules\Task\Controllers\RecurringTaskController;
use App\Modules\Task\Controllers\TaskController;
use App\Modules\Task\Controllers\TaskDeliverableController;
use App\Modules\Task\Controllers\TaskDependencyController;
use App\Modules\Task\Controllers\TaskTemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('tasks/my-assigned', [TaskController::class, 'myAssignedTasks'])
        ->name('tasks.my-assigned');

    Route::apiResource('tasks', TaskController::class);

    Route::get('tasks/{task}/dependencies', [TaskDependencyController::class, 'index'])
        ->name('tasks.dependencies.index');
    Route::post('tasks/{task}/dependencies', [TaskDependencyController::class, 'store'])
        ->name('tasks.dependencies.store');
    Route::delete('task-dependencies/{dependency}', [TaskDependencyController::class, 'destroy'])
        ->name('task-dependencies.destroy');

    Route::apiResource('task-templates', TaskTemplateController::class);
    Route::post('task-templates/{template}/instantiate', [TaskTemplateController::class, 'instantiate'])
        ->name('task-templates.instantiate');

    Route::apiResource('recurring-tasks', RecurringTaskController::class);
    Route::post('recurring-tasks/{config}/generate', [RecurringTaskController::class, 'generate'])
        ->name('recurring-tasks.generate');

    Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus'])
        ->name('tasks.update-status');
    Route::post('tasks/{task}/mark-complete', [TaskController::class, 'markComplete'])
        ->name('tasks.mark-complete');
    Route::get('tasks/{task}/children', [TaskController::class, 'children'])
        ->name('tasks.children');

    Route::post('tasks/{task}/assignees', [TaskController::class, 'addAssignee'])
        ->name('tasks.assignees.add');
    Route::delete('tasks/{task}/assignees/{userId}', [TaskController::class, 'removeAssignee'])
        ->name('tasks.assignees.remove');

    Route::get('tasks/{task}/test-scenarios', [TaskController::class, 'testScenarios'])
        ->name('tasks.test-scenarios.index');
    Route::post('tasks/{task}/test-scenarios', [TaskController::class, 'addTestScenario'])
        ->name('tasks.test-scenarios.add');
    Route::post('test-scenarios/{scenario}/complete', [TaskController::class, 'completeTestScenario'])
        ->name('test-scenarios.complete');
    Route::post('test-scenarios/{scenario}/verify', [TaskController::class, 'verifyTestScenario'])
        ->name('test-scenarios.verify');

    Route::get('tasks/{task}/activities', [TaskController::class, 'activities'])
        ->name('tasks.activities');
    Route::post('tasks/{task}/comments', [TaskController::class, 'addComment'])
        ->name('tasks.comments.add');

    Route::get('tags', [TaskController::class, 'tags'])
        ->name('tags.index');

    Route::get('projects/{projectId}/tasks', [TaskController::class, 'projectTasks'])
        ->name('projects.tasks');
    Route::get('projects/{projectId}/points-summary', [TaskController::class, 'pointsSummary'])
        ->name('projects.points-summary');

    Route::get('tasks/{taskId}/deliverables', [TaskDeliverableController::class, 'index'])
        ->name('tasks.deliverables.index');
    Route::post('tasks/{taskId}/deliverables', [TaskDeliverableController::class, 'store'])
        ->name('tasks.deliverables.store');
    Route::get('tasks/{taskId}/deliverables/{deliverableId}', [TaskDeliverableController::class, 'show'])
        ->name('tasks.deliverables.show');
    Route::put('tasks/{taskId}/deliverables/{deliverableId}', [TaskDeliverableController::class, 'update'])
        ->name('tasks.deliverables.update');
    Route::delete('tasks/{taskId}/deliverables/{deliverableId}', [TaskDeliverableController::class, 'destroy'])
        ->name('tasks.deliverables.destroy');
    Route::post('tasks/{taskId}/deliverables/{deliverableId}/submit', [TaskDeliverableController::class, 'submit'])
        ->name('tasks.deliverables.submit');
    Route::post('tasks/{taskId}/deliverables/{deliverableId}/review', [TaskDeliverableController::class, 'review'])
        ->name('tasks.deliverables.review');
});
