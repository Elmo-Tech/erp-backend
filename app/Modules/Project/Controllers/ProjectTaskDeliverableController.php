<?php

namespace App\Modules\Project\Controllers;

use App\Enums\ProjectRole;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Project\Actions\ProjectHistoryAction;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTaskDeliverable;
use App\Modules\Project\Requests\StoreTaskDeliverableRequest;
use App\Modules\Project\Resources\ProjectTaskDeliverableResource;
use App\Modules\Settings\Models\User;
use Illuminate\Http\JsonResponse;

class ProjectTaskDeliverableController extends Controller
{
    public function __construct(
        private readonly ProjectHistoryAction $historyAction
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * POST /projects/{project}/tasks/{task}/deliverables
     *
     * SECURITY: STRICTLY enforces manage_deliverables permission.
     * Only users with manage_deliverables OR manage_all_projects may add
     * deliverables. Developers and QA are execution-only.
     */
    public function store(StoreTaskDeliverableRequest $request, Project $project, ProjectTask $task): JsonResponse
    {
        $this->assertTaskBelongsToProject($task, $project);

        // ── PBAC: Deliverable creation guard ─────────────────────────────────
        $user = $request->user();

        if (! $this->canManageDeliverables($user, $project)) {
            return ApiResponse::error(
                'You do not have permission to add deliverables.',
                403
            );
        }

        $deliverable = $task->deliverables()->create([
            'title' => $request->validated()['title'],
            'is_completed' => false,
        ]);

        // Record activity for deliverable creation
        $this->historyAction->recordChange(
            $project,
            'deliverable_added',
            '',
            $deliverable->title,
            "Deliverable added to task '{$task->title}'"
        );

        return ApiResponse::success(
            new ProjectTaskDeliverableResource($deliverable),
            'Deliverable added successfully',
            201
        );
    }

    /**
     * PATCH /projects/{project}/tasks/{task}/deliverables/{deliverable}
     * Rename a deliverable.
     */
    public function update(StoreTaskDeliverableRequest $request, Project $project, ProjectTask $task, ProjectTaskDeliverable $deliverable): JsonResponse
    {
        $this->assertTaskBelongsToProject($task, $project);
        $this->assertDeliverableBelongsToTask($deliverable, $task);

        if (! $this->canManageDeliverables($request->user(), $project)) {
            return ApiResponse::error(
                'You do not have permission to edit deliverables.',
                403
            );
        }

        $oldTitle = $deliverable->title;
        $deliverable->update(['title' => $request->validated()['title']]);
        $deliverable->refresh();

        $this->historyAction->recordChange(
            $project,
            'deliverable_updated',
            $oldTitle,
            $deliverable->title,
            "Deliverable renamed in task '{$task->title}'"
        );

        return ApiResponse::success(
            new ProjectTaskDeliverableResource($deliverable),
            'Deliverable updated successfully'
        );
    }

    /**
     * PATCH /projects/{project}/tasks/{task}/deliverables/{deliverable}/toggle
     * Toggles the is_completed flag on a single deliverable.
     */
    public function toggle(Project $project, ProjectTask $task, ProjectTaskDeliverable $deliverable): JsonResponse
    {
        $this->assertTaskBelongsToProject($task, $project);
        $this->assertDeliverableBelongsToTask($deliverable, $task);
        $this->authorize('execute', $task);

        $oldStatus = $deliverable->is_completed;
        $deliverable->update(['is_completed' => ! $deliverable->is_completed]);
        $deliverable->refresh();

        // Record activity for deliverable toggle
        $this->historyAction->recordChange(
            $project,
            'deliverable_toggled',
            $oldStatus ? 'completed' : 'incomplete',
            $deliverable->is_completed ? 'completed' : 'incomplete',
            "Deliverable '{$deliverable->title}' in task '{$task->title}' marked as ".($deliverable->is_completed ? 'complete' : 'incomplete')
        );

        return ApiResponse::success(
            new ProjectTaskDeliverableResource($deliverable),
            $deliverable->is_completed ? 'Deliverable marked complete' : 'Deliverable marked incomplete'
        );
    }

    /**
     * DELETE /projects/{project}/tasks/{task}/deliverables/{deliverable}
     *
     * SECURITY: Requires manage_deliverables OR manage_all_projects.
     */
    public function destroy(Project $project, ProjectTask $task, ProjectTaskDeliverable $deliverable): JsonResponse
    {
        $user = request()->user();

        if (! $user?->can('delete_project_task_deliverables') && ! $this->canManageDeliverables($user, $project)) {
            return ApiResponse::error(
                'You do not have permission to delete deliverables.',
                403
            );
        }

        $this->assertTaskBelongsToProject($task, $project);
        $this->assertDeliverableBelongsToTask($deliverable, $task);

        $deliverable->delete();

        return ApiResponse::success(null, 'Deliverable deleted successfully');
    }

    // -------------------------------------------------------------------------

    private function assertTaskBelongsToProject(ProjectTask $task, Project $project): void
    {
        if ($task->project_id !== $project->id) {
            abort(404, 'Task not found for this project.');
        }
    }

    private function assertDeliverableBelongsToTask(ProjectTaskDeliverable $deliverable, ProjectTask $task): void
    {
        if ($deliverable->task_id !== $task->id) {
            abort(404, 'Deliverable not found for this task.');
        }
    }

    private function canManageDeliverables(?User $user, Project $project): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->can('manage_all_projects') || $user->can('manage_deliverables')) {
            return true;
        }

        if (! $user->can('edit_tasks') && ! $user->can('manage_tasks')) {
            return false;
        }

        $member = $project->members()->where('user_id', $user->id)->first();
        $role = $member?->role instanceof ProjectRole ? $member->role->value : (string) ($member?->role ?? '');

        return in_array($role, [
            ProjectRole::MANAGER->value,
            ProjectRole::TECH_LEAD->value,
        ], true);
    }
}
