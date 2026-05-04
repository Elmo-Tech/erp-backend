<?php

namespace App\Modules\Project\Controllers;

use App\Enums\ProjectRole;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTaskDeliverable;
use App\Modules\Project\Requests\StoreTaskDeliverableRequest;
use App\Modules\Project\Resources\ProjectTaskDeliverableResource;
use Illuminate\Http\JsonResponse;

class ProjectTaskDeliverableController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * POST /projects/{project}/tasks/{task}/deliverables
     *
     * SECURITY: Only manager, tech_lead, or global admin/founder roles may add
     * deliverables. Developers and QA are restricted to execution-only actions.
     */
    public function store(StoreTaskDeliverableRequest $request, Project $project, ProjectTask $task): JsonResponse
    {
        $this->assertTaskBelongsToProject($task, $project);

        // ── RBAC: Deliverable creation guard ──────────────────────────────────
        $user = $request->user();
        $isGlobalAdmin = $user->hasAnyRole(['admin', 'super-admin'])
            || $user->can('manage_all_projects');

        if (! $isGlobalAdmin) {
            $member = $project->members()->where('user_id', $user->id)->first();
            $restrictedRoles = [ProjectRole::DEVELOPER->value, ProjectRole::QA->value];

            if (! $member || in_array($member->role, $restrictedRoles, true)) {
                return ApiResponse::error(
                    'Only managers and tech leads may add deliverables to tasks.',
                    403
                );
            }
        }

        $deliverable = $task->deliverables()->create([
            'title'        => $request->validated()['title'],
            'is_completed' => false,
        ]);

        return ApiResponse::success(
            new ProjectTaskDeliverableResource($deliverable),
            'Deliverable added successfully',
            201
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

        $deliverable->update(['is_completed' => ! $deliverable->is_completed]);

        return ApiResponse::success(
            new ProjectTaskDeliverableResource($deliverable->refresh()),
            $deliverable->is_completed ? 'Deliverable marked complete' : 'Deliverable marked incomplete'
        );
    }

    /**
     * DELETE /projects/{project}/tasks/{task}/deliverables/{deliverable}
     */
    public function destroy(Project $project, ProjectTask $task, ProjectTaskDeliverable $deliverable): JsonResponse
    {
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
}
