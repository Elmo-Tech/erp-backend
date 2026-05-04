<?php

namespace App\Modules\Project\Controllers;

use App\Enums\ProjectRole;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Project\Actions\CompleteProjectTaskAction;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Requests\StoreProjectTaskRequest;
use App\Modules\Project\Requests\UpdateProjectTaskRequest;
use App\Modules\Project\Resources\ProjectTaskResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ProjectTaskController extends Controller
{
    public function __construct(
        private readonly CompleteProjectTaskAction $completeTask,
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * GET /projects/{project}/tasks
     * Returns root-level tasks (no parent). Subtasks are nested under each task.
     * Pass ?milestone_id=X to filter by milestone, or ?backlog=1 for backlog tasks.
     */
    public function index(Project $project): JsonResponse
    {
        $query = $project->executionTasks()
            ->root()
            ->with(['assignee', 'deliverables', 'subtasks.assignee', 'milestone', 'timeEntries']);

        if (request()->filled('milestoneId')) {
            $query->where('milestone_id', request()->integer('milestoneId'));
        }

        if (request()->boolean('backlog')) {
            $query->backlog();
        }

        $tasks = $query->orderBy('created_at')->get();

        return ApiResponse::success(
            ['tasks' => ProjectTaskResource::collection($tasks)],
            'Tasks retrieved successfully'
        );
    }

    /**
     * POST /projects/{project}/tasks
     */
    public function store(StoreProjectTaskRequest $request, Project $project): JsonResponse
    {
        $data = $request->validated();

        $task = $project->executionTasks()->create([
            'milestone_id'    => $data['milestoneId'] ?? null,
            'parent_id'       => $data['parentId'] ?? null,
            'title'           => $data['title'],
            'description'     => $data['description'] ?? null,
            'status'          => $data['status'] ?? 'to_do',
            'estimated_hours' => $data['estimatedHours'] ?? null,
            'assignee_id'     => $data['assigneeId'] ?? null,
        ]);

        $task->load(['assignee', 'deliverables', 'milestone']);

        return ApiResponse::success(
            new ProjectTaskResource($task),
            'Task created successfully',
            201
        );
    }

    /**
     * GET /projects/{project}/tasks/{task}
     */
    public function show(Project $project, ProjectTask $task): JsonResponse
    {
        $this->assertBelongsToProject($task, $project);

        $task->load(['assignee', 'deliverables', 'subtasks.assignee', 'timeEntries.user', 'milestone', 'parent']);

        return ApiResponse::success(
            new ProjectTaskResource($task),
            'Task retrieved successfully'
        );
    }

    /**
     * PATCH /projects/{project}/tasks/{task}
     *
     * SECURITY: When a status transition is requested, enforce role-based
     * workflow rules:
     *   - developer  → may only set in_progress or in_review
     *   - qa         → may set in_progress (reject), qa, or done
     *   - manager / tech_lead / admin → unrestricted
     */
    public function update(UpdateProjectTaskRequest $request, Project $project, ProjectTask $task): JsonResponse
    {
        $this->assertBelongsToProject($task, $project);

        $data = $request->validated();

        // ── RBAC: Status transition guard ─────────────────────────────────────
        if (isset($data['status'])) {
            $user = $request->user();
            $isGlobalAdmin = $user->hasAnyRole(['admin', 'super-admin'])
                || $user->can('manage_all_projects');

            if (! $isGlobalAdmin) {
                $member = $project->members()->where('user_id', $user->id)->first();
                $projectRole = $member?->role;

                $allowedByRole = match ($projectRole) {
                    ProjectRole::DEVELOPER->value => ['in_progress', 'in_review'],
                    ProjectRole::QA->value        => ['in_progress', 'qa', 'done'],
                    // manager, tech_lead, and unmapped roles get full access
                    default                       => null,
                };

                if ($allowedByRole !== null && ! in_array($data['status'], $allowedByRole, true)) {
                    return ApiResponse::error(
                        "Your project role ({$projectRole}) does not permit setting status to '{$data['status']}'.",
                        403
                    );
                }
            }
        }

        $task->update(array_filter([
            'milestone_id'    => array_key_exists('milestoneId', $data) ? $data['milestoneId'] : $task->milestone_id,
            'parent_id'       => array_key_exists('parentId', $data) ? $data['parentId'] : $task->parent_id,
            'title'           => $data['title'] ?? null,
            'description'     => array_key_exists('description', $data) ? $data['description'] : $task->description,
            'status'          => $data['status'] ?? null,
            'estimated_hours' => array_key_exists('estimatedHours', $data) ? $data['estimatedHours'] : $task->estimated_hours,
            'assignee_id'     => array_key_exists('assigneeId', $data) ? $data['assigneeId'] : $task->assignee_id,
        ], fn ($v) => $v !== null));

        $task->load(['assignee', 'deliverables', 'milestone']);

        return ApiResponse::success(
            new ProjectTaskResource($task->refresh()),
            'Task updated successfully'
        );
    }

    /**
     * DELETE /projects/{project}/tasks/{task}
     */
    public function destroy(Project $project, ProjectTask $task): JsonResponse
    {
        $this->assertBelongsToProject($task, $project);

        $task->delete();

        return ApiResponse::success(null, 'Task deleted successfully');
    }

    /**
     * POST /projects/{project}/tasks/{task}/complete
     * Marks the task as DONE after verifying all deliverables are checked off.
     *
     * SECURITY: Developers are forbidden from calling this endpoint directly.
     * Their workflow ceiling is `in_review`; only QA, managers, tech leads,
     * and global admins may mark a task as done via this route.
     */
    public function complete(Project $project, ProjectTask $task): JsonResponse
    {
        $this->assertBelongsToProject($task, $project);

        // ── RBAC: Completion guard ────────────────────────────────────────────
        $user = request()->user();
        $isGlobalAdmin = $user->hasAnyRole(['admin', 'super-admin'])
            || $user->can('manage_all_projects');

        if (! $isGlobalAdmin) {
            $member = $project->members()->where('user_id', $user->id)->first();
            $projectRole = $member?->role;

            // Developers may never directly complete a task — they submit for review
            if ($projectRole === ProjectRole::DEVELOPER->value) {
                return ApiResponse::error(
                    'Developers cannot mark tasks as done. Submit for review instead.',
                    403
                );
            }
        }

        try {
            $task = $this->completeTask->execute($task);
        } catch (ValidationException $e) {
            return ApiResponse::validationError(
                $e->errors(),
                $e->getMessage()
            );
        }

        $task->load(['assignee', 'deliverables', 'milestone']);

        return ApiResponse::success(
            new ProjectTaskResource($task),
            'Task marked as done'
        );
    }

    // -------------------------------------------------------------------------

    private function assertBelongsToProject(ProjectTask $task, Project $project): void
    {
        if ($task->project_id !== $project->id) {
            abort(404, 'Task not found for this project.');
        }
    }
}
