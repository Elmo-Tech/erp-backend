<?php

namespace App\Modules\Project\Controllers;

use App\Enums\ProjectRole;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\HR\Actions\EvaluateQABugPointsAction;
use App\Modules\Notification\Notifications\ProjectTaskAssignedNotification;
use App\Modules\Project\Actions\CompleteProjectTaskAction;
use App\Modules\Project\Actions\ProjectHistoryAction;
use App\Modules\Project\Enums\ExecutionTaskStatus;
use App\Modules\Settings\Models\User;
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
        private readonly EvaluateQABugPointsAction $evaluateQABugPoints,
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * GET /projects/{project}/tasks
     * Returns all tasks (flat structure - subtasks removed).
     * Pass ?milestone_id=X to filter by milestone, or ?backlog=1 for backlog tasks.
     *
     * SECURITY: Scoped by project membership.
     *   - manage_all_projects → sees all tasks.
     *   - manager/tech_lead   → sees all tasks in their project.
     *   - developer/qa/designer → sees ONLY tasks assigned to them.
     */
    public function index(Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        $this->authorize('viewAny', [ProjectTask::class, $project]);

        $user = request()->user();

        $query = $project->executionTasks()
            ->with(['assignee', 'deliverables', 'milestone', 'timeEntries.user']);
            // REMOVED: subtasks.assignee - subtasks are no longer supported

        // ── ABAC: Task isolation ──────────────────────────────────────────────
        // Global admins and project managers see all tasks.
        // Developers, QA, and designers only see tasks assigned to them.
        if (! $user->can('manage_all_projects') && ! $user->can('manage_tasks')) {
            $member      = $project->members()->where('user_id', $user->id)->first();
            $projectRole = $member?->role instanceof \App\Enums\ProjectRole
                ? $member->role->value
                : (string) ($member?->role ?? '');

            $isManagerLevel = in_array($projectRole, [
                \App\Enums\ProjectRole::MANAGER->value,
                \App\Enums\ProjectRole::TECH_LEAD->value,
            ], true);

            if (! $isManagerLevel) {
                // Worker-level: only see own assigned tasks
                $query->where('assignee_id', $user->id);
            }
        }

        $request = request();
        $filter = (array) $request->input('filter', []);

        $milestoneId = $request->input('milestoneId', $filter['milestoneId'] ?? null);
        if ($milestoneId !== null && $milestoneId !== '') {
            $query->where('milestone_id', (int) $milestoneId);
        }

        $backlog = $request->input('backlog', $filter['isInBacklog'] ?? null);
        if (filter_var($backlog, FILTER_VALIDATE_BOOLEAN)) {
            $query->backlog();
        }

        if (! empty($filter['status'])) {
            $query->where('status', $filter['status']);
        }

        if (! empty($filter['priority'])) {
            $query->where('priority', $filter['priority']);
        }

        if (! empty($filter['assigneeId'])) {
            $query->where('assignee_id', (int) $filter['assigneeId']);
        }

        if (! empty($filter['search'])) {
            $value = (string) $filter['search'];
            $query->where('title', 'like', "%{$value}%");
        }

        if (! empty($filter['dueFrom'])) {
            $query->whereDate('due_date', '>=', $filter['dueFrom']);
        }

        if (! empty($filter['dueTo'])) {
            $query->whereDate('due_date', '<=', $filter['dueTo']);
        }

        $tasks = $query->orderBy('created_at')->get();

        return ApiResponse::success(
            ['tasks' => ProjectTaskResource::collection($tasks)],
            'Tasks retrieved successfully'
        );
    }

    /**
     * GET /projects/tasks/my-active
     * Returns non-completed execution tasks assigned to the authenticated user
     * across all projects.
     */
    public function myActive(): JsonResponse
    {
        $tasks = ProjectTask::query()
            ->with(['assignee', 'deliverables', 'milestone', 'timeEntries.user', 'project'])
            ->where('assignee_id', request()->user()->id)
            ->where('status', '!=', ExecutionTaskStatus::DONE->value)
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->orderByDesc('updated_at')
            ->get();

        return ApiResponse::success(
            ['tasks' => ProjectTaskResource::collection($tasks)],
            'My active tasks retrieved successfully'
        );
    }

    /**
     * POST /projects/{project}/tasks
     *
     * SECURITY: Only project managers/tech leads or global admins may create tasks.
     */
    public function store(StoreProjectTaskRequest $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        $this->authorize('create', [ProjectTask::class, $project]);
        $data = $request->validated();

        $task = $project->executionTasks()->create([
            'milestone_id'    => $data['milestoneId'] ?? null,
            // REMOVED: parent_id - subtasks are no longer supported
            'title'           => $data['title'],
            'description'     => $data['description'] ?? null,
            'status'          => $data['status'] ?? 'to_do',
            'priority'        => $data['priority'] ?? 'medium',
            'estimated_hours' => $data['estimatedHours'] ?? null,
            'start_date'      => $data['startDate'] ?? null,
            'due_date'        => $data['dueDate'] ?? null,
            'assignee_id'     => $data['assigneeId'] ?? null,
        ]);

        // Record activity for task creation
        $assigneeName = isset($data['assigneeId']) ? User::find($data['assigneeId'])?->name ?? "User #{$data['assigneeId']}" : 'Unassigned';

        $this->historyAction->recordChange(
            $project,
            'task_created',
            '',
            $task->title,
            "Task created and assigned to {$assigneeName}"
        );

        $task->load(['assignee', 'deliverables', 'milestone', 'timeEntries.user']);

        if ($task->assignee !== null) {
            $task->assignee->notify(new ProjectTaskAssignedNotification($task));
        }

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
        $this->authorize('view', $project);
        $this->assertBelongsToProject($task, $project);
        $this->authorize('view', $task);

        $task->load(['assignee', 'deliverables', 'timeEntries.user', 'milestone']);
        // REMOVED: subtasks.assignee, parent - subtasks are no longer supported

        return ApiResponse::success(
            new ProjectTaskResource($task),
            'Task retrieved successfully'
        );
    }

    /**
     * PATCH /projects/{project}/tasks/{task}
     *
     * SECURITY: Policy enforces that only the assignee (work_on_tasks) or a
     * project manager/tech_lead (edit_tasks) or global admin may update a task.
     *
     * CRITICAL WORKFLOW ENFORCEMENT:
     *   - ONLY users with manage_deliverables, manage_all_projects, OR project manager role
     *     can mark a task as "done" (completed).
     *   - Regular assignees CANNOT directly set status to "done". Their ceiling is "in_review".
     *   - If an assignee tries to set status to "done", return 403 with clear message.
     */
    public function update(UpdateProjectTaskRequest $request, Project $project, ProjectTask $task): JsonResponse
    {
        $this->authorize('view', $project);
        $this->assertBelongsToProject($task, $project);
        $this->authorize('update', $task);

        $data = $request->validated();
        $user = $request->user();

        if ($this->containsMetadataUpdate($data) && ! $this->canEditTaskMetadata($user, $project)) {
            return ApiResponse::error(
                'You do not have permission to edit task details.',
                403
            );
        }

        // ── CRITICAL: "COMPLETED" STATUS GUARD ────────────────────────────────
        // If the user is trying to set status to "done", verify they have the authority.
        // ONLY these users can mark tasks as completed:
        //   1. manage_all_projects (global admin bypass)
        //   2. manage_deliverables (deliverable managers)
        //   3. manage_tasks (task managers)
        //   4. Project manager or tech_lead role
        //
        // Regular assignees (developers, QA without special permissions) CANNOT
        // mark tasks as "done". They must use "in_review" instead.
        if (isset($data['status']) && $data['status'] === ExecutionTaskStatus::DONE->value) {
            // Global bypass permissions
            if ($user->can('manage_all_projects') || $user->can('manage_deliverables') || $user->can('manage_tasks')) {
                // Allowed — proceed
            } else {
                // Check if user is project manager or tech_lead
                $member = $project->members()->where('user_id', $user->id)->first();
                $projectRole = $member?->role instanceof ProjectRole
                    ? $member->role->value
                    : (string) ($member?->role ?? '');

                $isManagerLevel = in_array($projectRole, [
                    ProjectRole::MANAGER->value,
                    ProjectRole::TECH_LEAD->value,
                ], true);

                if (! $isManagerLevel) {
                    return ApiResponse::error(
                        'You cannot mark tasks as completed directly. Please use "In Review" status instead.',
                        403
                    );
                }
            }
        }

        $oldStatus = $task->status;
        $oldAssignee = $task->assignee_id;

        $updateData = [];

        foreach ([
            'milestoneId'    => 'milestone_id',
            'title'          => 'title',
            'description'    => 'description',
            'status'         => 'status',
            'priority'       => 'priority',
            'estimatedHours' => 'estimated_hours',
            'startDate'      => 'start_date',
            'dueDate'        => 'due_date',
            'assigneeId'     => 'assignee_id',
        ] as $requestKey => $column) {
            if (array_key_exists($requestKey, $data)) {
                $updateData[$column] = $data[$requestKey];
            }
        }

        $task->update($updateData);

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

            // ── GAMIFICATION: QA rejection detection ─────────────────────────
            // A QA rejection is defined as moving BACKWARD from a review/QA
            // stage to a development stage:
            //   in_review → in_progress  (code review rejection)
            //   qa        → in_progress  (QA tester rejection)
            $fromStatus = $oldStatus instanceof ExecutionTaskStatus
                ? $oldStatus->value
                : (string) $oldStatus;

            $toStatus = $data['status'];

            $isQaRejection = in_array($fromStatus, [
                ExecutionTaskStatus::IN_REVIEW->value,
                ExecutionTaskStatus::QA->value,
            ], true) && $toStatus === ExecutionTaskStatus::IN_PROGRESS->value;

            if ($isQaRejection) {
                $this->evaluateQABugPoints->execute($task);
            }
        }

        // Record activity for assignee changes
        if (array_key_exists('assigneeId', $data) && $oldAssignee !== $data['assigneeId']) {
            $oldAssigneeName = $oldAssignee ? User::find($oldAssignee)?->name ?? "User #{$oldAssignee}" : 'Unassigned';
            $newAssigneeName = $data['assigneeId'] ? User::find($data['assigneeId'])?->name ?? "User #{$data['assigneeId']}" : 'Unassigned';

            $this->historyAction->recordChange(
                $project,
                'task_assignee_changed',
                $oldAssigneeName,
                $newAssigneeName,
                "Task '{$task->title}' reassigned"
            );

            $task->loadMissing('assignee');
            if ($task->assignee !== null) {
                $task->assignee->notify(new ProjectTaskAssignedNotification($task));
            }
        }

        $task->load(['assignee', 'deliverables', 'milestone', 'timeEntries.user']);

        return ApiResponse::success(
            new ProjectTaskResource($task),
            'Task updated successfully'
        );
    }

    /**
     * DELETE /projects/{project}/tasks/{task}
     *
     * SECURITY: Only project managers/tech leads or global admins may delete tasks.
     */
    public function destroy(Project $project, ProjectTask $task): JsonResponse
    {
        $this->assertBelongsToProject($task, $project);
        $this->authorize('delete', $task);

        $task->delete();

        return ApiResponse::success(null, 'Task deleted successfully');
    }

    /**
     * POST /projects/{project}/tasks/{task}/complete
     * Marks the task as DONE after verifying all deliverables are checked off.
     *
     * SECURITY: TaskPolicy::complete() enforces that developers cannot call this
     * endpoint directly. Their workflow ceiling is `in_review`.
     * Only QA, managers, tech leads, and global admins may mark a task as done.
     */
    public function complete(Project $project, ProjectTask $task): JsonResponse
    {
        $this->authorize('view', $project);
        $this->assertBelongsToProject($task, $project);
        $this->authorize('complete', $task);

        try {
            $task = $this->completeTask->execute($task);
        } catch (ValidationException $e) {
            return ApiResponse::validationError(
                $e->errors(),
                $e->getMessage()
            );
        }

        $task->load(['assignee', 'deliverables', 'milestone', 'timeEntries.user']);

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

    /**
     * @param  array<string, mixed>  $data
     */
    private function containsMetadataUpdate(array $data): bool
    {
        $metadataFields = [
            'milestoneId' => true,
            'title' => true,
            'description' => true,
            'priority' => true,
            'estimatedHours' => true,
            'startDate' => true,
            'dueDate' => true,
            'assigneeId' => true,
        ];

        return array_intersect_key($data, $metadataFields) !== [];
    }

    private function canEditTaskMetadata(User $user, Project $project): bool
    {
        if ($user->can('manage_all_projects') || $user->can('manage_tasks')) {
            return true;
        }

        if (! $user->can('edit_tasks')) {
            return false;
        }

        $member = $project->members()->where('user_id', $user->id)->first();
        $projectRole = $member?->role instanceof ProjectRole
            ? $member->role->value
            : (string) ($member?->role ?? '');

        return in_array($projectRole, [
            ProjectRole::MANAGER->value,
            ProjectRole::TECH_LEAD->value,
        ], true);
    }
}
