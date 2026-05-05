<?php

namespace App\Modules\Project\Controllers;

use App\Enums\ProjectRole;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Settings\Models\User;
use App\Modules\Project\Actions\CompleteProjectTaskAction;
use App\Modules\Project\Actions\ProjectHistoryAction;
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
        private readonly ProjectHistoryAction $historyAction,
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
            'milestone_id' => $data['milestoneId'] ?? null,
            'parent_id' => $data['parentId'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'to_do',
            'estimated_hours' => $data['estimatedHours'] ?? null,
            'assignee_id' => $data['assigneeId'] ?? null,
        ]);

        // Record activity for task creation
        $taskType = isset($data['parentId']) ? 'Subtask' : 'Task';
        $assigneeName = isset($data['assigneeId']) ? User::find($data['assigneeId'])?->name ?? "User #{$data['assigneeId']}" : 'Unassigned';

        $this->historyAction->recordChange(
            $project,
            'task_created',
            '',
            $task->title,
            "{$taskType} created and assigned to {$assigneeName}"
        );

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

        // ── PBAC: Status transition guard ────────────────────────────────────
        // manage_all_projects → unrestricted status transitions.
        // Otherwise, enforce project-membership-role workflow rules:
        //   developer  → may only set in_progress or in_review
        //   qa         → may set in_progress (reject), qa, or done
        //   manager / tech_lead / unmapped → unrestricted
        if (isset($data['status'])) {
            $user = $request->user();

            if (! $user->can('manage_all_projects')) {
                $member = $project->members()->where('user_id', $user->id)->first();
                $projectRole = $member?->role;

                $allowedByRole = match ($projectRole) {
                    ProjectRole::DEVELOPER->value => ['in_progress', 'in_review'],
                    ProjectRole::QA->value => ['in_progress', 'qa', 'done'],
                    default => null, // manager, tech_lead → unrestricted
                };

                if ($allowedByRole !== null && ! in_array($data['status'], $allowedByRole, true)) {
                    return ApiResponse::error(
                        "Your project role ({$projectRole}) does not permit setting status to '{$data['status']}'.",
                        403
                    );
                }
            }
        }

        $oldStatus = $task->status;
        $oldAssignee = $task->assignee_id;

        $task->update(array_filter([
            'milestone_id' => array_key_exists('milestoneId', $data) ? $data['milestoneId'] : $task->milestone_id,
            'parent_id' => array_key_exists('parentId', $data) ? $data['parentId'] : $task->parent_id,
            'title' => $data['title'] ?? null,
            'description' => array_key_exists('description', $data) ? $data['description'] : $task->description,
            'status' => $data['status'] ?? null,
            'estimated_hours' => array_key_exists('estimatedHours', $data) ? $data['estimatedHours'] : $task->estimated_hours,
            'assignee_id' => array_key_exists('assigneeId', $data) ? $data['assigneeId'] : $task->assignee_id,
        ], fn ($v) => $v !== null));

        $task->refresh();

        // Record activity for status changes
        if (isset($data['status']) && $oldStatus !== $data['status']) {
            $reason = isset($data['reason']) ? $data['reason'] : null;
            $this->historyAction->recordChange(
                $project,
                'task_status_changed',
                $oldStatus,
                $data['status'],
                $reason ?? "Task '{$task->title}' status changed"
            );
        }

        // Record activity for assignee changes
        if (isset($data['assigneeId']) && $oldAssignee !== $data['assigneeId']) {
            $oldAssigneeName = $oldAssignee ? User::find($oldAssignee)?->name ?? "User #{$oldAssignee}" : 'Unassigned';
            $newAssigneeName = $data['assigneeId'] ? User::find($data['assigneeId'])?->name ?? "User #{$data['assigneeId']}" : 'Unassigned';

            $this->historyAction->recordChange(
                $project,
                'task_assignee_changed',
                $oldAssigneeName,
                $newAssigneeName,
                "Task '{$task->title}' reassigned"
            );
        }

        $task->load(['assignee', 'deliverables', 'milestone']);

        return ApiResponse::success(
            new ProjectTaskResource($task),
            'Task updated successfully'
        );
    }

    /**
     * DELETE /projects/{project}/tasks/{task}
     */
    public function destroy(Project $project, ProjectTask $task): JsonResponse
    {
        abort_if(! request()->user()?->can('delete_tasks'), 403, 'Unauthorized');

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

        // ── PBAC: Completion guard ────────────────────────────────────────────
        // manage_all_projects → may complete any task.
        // Otherwise, project-membership-role rules apply:
        //   developer → ceiling is in_review; they may never directly complete.
        $user = request()->user();

        if (! $user->can('manage_all_projects')) {
            $member = $project->members()->where('user_id', $user->id)->first();
            $projectRole = $member?->role;

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
