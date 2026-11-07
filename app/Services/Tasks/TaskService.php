<?php

namespace App\Services\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Tag;
use App\Models\Project;
use App\Models\LookupOption;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskAssignee;
use App\Models\TaskTestScenario;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TaskService
{
    public function __construct(
        private TaskNotificationService $taskNotificationService
    ) {}

    /** Compact company-wide list for the dashboard, respecting project visibility. */
    public function getDashboardTasks(User $user): array
    {
        $query = Task::query()->select(['id', 'project_id', 'assigned_to', 'title', 'status', 'priority', 'due_date', 'workflow_stage_id'])
            ->with(['project:id,title', 'assignee:id,name', 'assignees.user:id,name', 'workflowStage:id,name,color,position'])
            ->where(function ($query): void {
                $query->whereNull('project_id')->orWhereIn('project_id', Project::withoutGlobalScopes()->whereNull('deleted_at')->select('id'));
            });
        $this->applyProjectVisibility($query, $user);
        if ($user->specialization && ! ($user->can('view_all_projects') && $user->can('manage_tasks'))) {
            $query->visibleToUser($user);
        }
        $statuses = LookupOption::ofType('task_status')->get()->keyBy('value');
        $stagePositions = \App\Models\WorkflowTemplate::where('is_default', true)->where('is_active', true)->first()?->stages->groupBy('category')->map(fn ($stages) => $stages->first());
        return $query->orderByDesc('created_at')->orderByDesc('id')->get()->map(function (Task $task) use ($statuses, $stagePositions): array {
            $value = $task->status?->value ?? 'todo';
            $option = $statuses->get($value);
            $fallbackStage = $stagePositions?->get(match ($value) { 'in_progress' => 'active', 'on_hold' => 'paused', 'review', 'testing' => 'review', 'completed' => 'done', default => 'planned' });
            return [
                'taskId' => $task->id, 'title' => $task->title,
                'project' => $task->project?->title, 'projectId' => $task->project_id,
                'status' => $task->workflowStage?->name ?? $fallbackStage?->name ?? $option?->label ?? ucwords(str_replace('_', ' ', $value)),
                'statusColor' => $task->workflowStage?->color ?? $fallbackStage?->color ?? $option?->color,
                'statusPosition' => $task->workflowStage?->position ?? $fallbackStage?->position,
                'priority' => $task->priority?->value, 'dueDate' => $task->due_date?->format('Y-m-d'),
                'assignees' => $task->assignees->map(fn ($assignee) => $assignee->user?->name)->filter()->values()->all() ?: array_values(array_filter([$task->assignee?->name])),
            ];
        })->all();
    }

    /**
     * Get all tasks with optional filtering and pagination.
     */
    public function getAllTasks(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Task::query()->with(['assignee', 'supervisor', 'project', 'tags', 'assignees.user', 'workflowStage']);

        // Apply user specialization filtering if user is authenticated
        $user = auth()->user();
        if ($user) {
            $this->applyProjectVisibility($query, $user);
        }
        if ($user && $user->specialization) {
            $query->visibleToUser($user);
        }

        if (! empty($filters['projectId'])) {
            $query->where('project_id', $filters['projectId']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['taskCategory'])) {
            $query->where('task_category', $filters['taskCategory']);
        }

        if (! empty($filters['assignedTo'])) {
            $query->where('assigned_to', $filters['assignedTo']);
        }

        // Filter by team member (user assigned in task_assignees table)
        if (! empty($filters['teamMember'])) {
            $query->whereHas('assignees', function ($q) use ($filters) {
                $q->where('user_id', $filters['teamMember']);
            });
        }

        if (array_key_exists('parentId', $filters)) {
            if ($filters['parentId'] === null) {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $filters['parentId']);
            }
        }

        if (! empty($filters['tagId'])) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $filters['tagId']));
        }

        if (! empty($filters['search'])) {
            $query->where('title', 'like', '%'.$filters['search'].'%');
        }

        // Relationship filters use EXISTS, so this query does not join or duplicate tasks.
        // DISTINCT over tasks.* fails on PostgreSQL when a task has JSON columns.
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get tasks where the user is assigned as a team member.
     * Returns ALL tasks (including children) where user is assigned.
     * This allows filtering by status to work correctly for leaf tasks.
     */
    public function getMyAssignedTasks(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Task::query()
            ->with([
                'assignee',
                'supervisor',
                'project',
                'tags',
                'assignees.user',
                'parent', // Load parent to show hierarchy
                // Load children (Weekly tasks) with their children (Subtasks)
                'children.assignee',
                'children.supervisor',
                'children.tags',
                'children.assignees.user',
                'children.children.assignee',
                'children.children.supervisor',
                'children.children.tags',
                'children.children.assignees.user',
            ])
            ->whereHas('assignees', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        $this->applyProjectVisibility($query, $user);
        // Removed whereNull('parent_id') to get ALL tasks where user is assigned

        // Apply user specialization filtering
        if ($user->specialization) {
            $query->visibleToUser($user);
        }

        // Apply additional filters
        if (! empty($filters['projectId'])) {
            $query->where('project_id', $filters['projectId']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['search'])) {
            $query->where('title', 'like', '%'.$filters['search'].'%');
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get tasks for a specific project with hierarchy support.
     */
    public function getTasksForProject(int $projectId, int $perPage = 15): LengthAwarePaginator
    {
        return Task::query()
            ->where('project_id', $projectId)
            ->where('is_pipeline_task', false)
            ->whereNull('parent_id')
            ->with([
                'assignee',
                'supervisor',
                'tags',
                'assignees.user',
                // Load children (Weekly tasks) with their children (Subtasks)
                'children.assignee',
                'children.supervisor',
                'children.tags',
                'children.assignees.user',
                'children.children.assignee',
                'children.children.supervisor',
                'children.children.tags',
                'children.children.assignees.user',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    private function applyProjectVisibility($query, User $user): void
    {
        if ($user->can('view_all_projects')) {
            return;
        }

        $query->where(function ($visibilityQuery) use ($user): void {
            $visibilityQuery
                ->whereHas('project.members', fn ($memberQuery) => $memberQuery->where('user_id', $user->id))
                ->orWhere(function ($unscopedQuery) use ($user): void {
                    $unscopedQuery
                        ->whereNull('project_id')
                        ->where(function ($ownershipQuery) use ($user): void {
                            $ownershipQuery
                                ->where('assigned_to', $user->id)
                                ->orWhere('created_by', $user->id);
                        });
                });
        });
    }

    /**
     * Get children of a specific task for lazy loading.
     * Also loads grandchildren (subtasks) for complete hierarchy.
     */
    public function getTaskChildren(int $taskId): Collection
    {
        return Task::query()
            ->where('parent_id', $taskId)
            ->with([
                'assignee',
                'supervisor',
                'tags',
                'assignees.user',
                'children.assignee',
                'children.supervisor',
                'children.tags',
                'children.assignees.user',
            ])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get a single task by ID with all relationships.
     */
    public function getTask(int $taskId): ?Task
    {
        return Task::with([
            'assignee',
            'supervisor',
            'project',
            'parent',
            'children.assignee',
            'children.supervisor',
            'children.tags',
            'tags',
            'assignees.user',
            'testScenarios.completedBy',
            'testScenarios.verifiedBy',
        ])->find($taskId);
    }

    /**
     * Create a new task.
     * For Weekly Tasks and Subtasks, assignees are inherited from the Main Task.
     *
     * When assignedTo (primary assignee) is provided during creation,
     * automatically adds them as a primary assignee in task_assignees table.
     */
    public function createTask(array $data, User $user): Task
    {
        $taskData = [
            'project_id' => $data['projectId'] ?? null,
            'parent_id' => $data['parentId'] ?? null,
            'assigned_to' => $data['assignedTo'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'acceptance_criteria' => $data['acceptanceCriteria'] ?? null,
            'status' => $data['status'] ?? TaskStatus::TODO->value,
            'type' => $data['type'] ?? TaskType::MAIN->value,
            'priority' => $data['priority'] ?? TaskPriority::MEDIUM->value,
            'task_category' => $data['taskCategory'] ?? null,
            'due_date' => $data['dueDate'] ?? null,
            'expected_resolution_date' => $data['expectedResolutionDate'] ?? null,
            'start_date' => $data['startDate'] ?? null,
            'sprint_number' => $data['sprintNumber'] ?? null,
            'estimated_hours' => $data['estimatedHours'] ?? null,
            'internal_reviewer_notes' => $data['internalReviewerNotes'] ?? null,
            'total_time_spent' => 0,
            'reopen_count' => 0,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];

        $task = Task::create($taskData);

        // Attach tags
        if (! empty($data['tagIds'])) {
            $task->tags()->attach($data['tagIds']);
        }

        // Handle assignees based on task type
        $taskType = $data['type'] ?? TaskType::MAIN->value;

        if ($taskType === TaskType::MAIN->value) {
            // Main Tasks: Add assignees directly (builds the team)
            if (! empty($data['assignees'])) {
                foreach ($data['assignees'] as $assignee) {
                    TaskAssignee::create([
                        'task_id' => $task->id,
                        'user_id' => $assignee['userId'],
                        'role' => $assignee['role'] ?? 'developer',
                        'is_primary' => $assignee['isPrimary'] ?? false,
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                }
            }

            // Auto-add primary assignee if assignedTo is set and not already in assignees
            if (! empty($data['assignedTo'])) {
                $existingAssigneeIds = collect($data['assignees'] ?? [])->pluck('userId')->toArray();
                if (! in_array($data['assignedTo'], $existingAssigneeIds)) {
                    TaskAssignee::create([
                        'task_id' => $task->id,
                        'user_id' => $data['assignedTo'],
                        'role' => 'developer',
                        'is_primary' => true,
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                }
            }
        } else {
            // Weekly Tasks and Subtasks: Can have specific assignees from the Main Task's team
            $mainTask = $this->findMainTask($task);
            $teamUserIds = $mainTask ? $mainTask->assignees()->pluck('user_id')->toArray() : [];

            // If assignees provided, validate they're in the team and add them
            if (! empty($data['assignees'])) {
                foreach ($data['assignees'] as $assignee) {
                    // Only add if user is in the Main Task's team
                    if (in_array($assignee['userId'], $teamUserIds)) {
                        TaskAssignee::create([
                            'task_id' => $task->id,
                            'user_id' => $assignee['userId'],
                            'role' => $assignee['role'] ?? 'developer',
                            'is_primary' => $assignee['isPrimary'] ?? false,
                            'created_by' => $user->id,
                            'updated_by' => $user->id,
                        ]);
                    }
                }
            }

            // Auto-add primary assignee if assignedTo is set
            if (! empty($data['assignedTo'])) {
                $existingAssigneeIds = $task->assignees()->pluck('user_id')->toArray();

                // Only add if not already assigned and is in the team
                if (! in_array($data['assignedTo'], $existingAssigneeIds)) {
                    if (in_array($data['assignedTo'], $teamUserIds)) {
                        TaskAssignee::create([
                            'task_id' => $task->id,
                            'user_id' => $data['assignedTo'],
                            'role' => 'developer',
                            'is_primary' => true,
                            'created_by' => $user->id,
                            'updated_by' => $user->id,
                        ]);
                    }
                }
            }
        }

        // Log activity
        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'type' => TaskActivity::TYPE_CREATED,
            'description' => 'Created task',
        ]);

        $task->loadMissing(['assignees.user', 'project', 'createdBy']);
        $assignedUsers = $task->assignees
            ->map(fn ($assignee) => $assignee->user)
            ->filter(fn ($assigneeUser) => $assigneeUser instanceof User)
            ->values();

        $this->taskNotificationService->sendTaskAssigned($task, $user, $assignedUsers);

        return $task->load(['assignee', 'supervisor', 'project', 'parent', 'children', 'tags', 'assignees.user']);
    }

    /**
     * Inherit assignees from the Main Task (root ancestor).
     */
    private function inheritAssigneesFromMainTask(Task $task, User $user): void
    {
        // Find the Main Task (root ancestor)
        $mainTask = $this->findMainTask($task);

        if (! $mainTask) {
            return;
        }

        // Copy all assignees from Main Task
        $mainTaskAssignees = $mainTask->assignees()->get();

        foreach ($mainTaskAssignees as $assignee) {
            TaskAssignee::create([
                'task_id' => $task->id,
                'user_id' => $assignee->user_id,
                'role' => $assignee->role,
                'is_primary' => $assignee->is_primary,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        }
    }

    /**
     * Find the Main Task (root ancestor) for a given task.
     */
    private function findMainTask(Task $task): ?Task
    {
        $current = $task;

        // Walk up the parent chain until we find a Main Task or reach the root
        while ($current->parent_id !== null) {
            $parent = Task::find($current->parent_id);

            if (! $parent) {
                break;
            }

            if ($parent->type === TaskType::MAIN) {
                return $parent;
            }

            $current = $parent;
        }

        // If current task's parent is null and it's a Main Task, return it
        if ($current->type === TaskType::MAIN) {
            return $current;
        }

        return null;
    }

    /**
     * Update an existing task.
     */
    public function updateTask(Task $task, array $data, User $user): Task
    {
        $oldStatus = $task->status?->value;
        $updateData = ['updated_by' => $user->id];

        // Map camelCase to snake_case
        $fieldMap = [
            'parentId' => 'parent_id',
            'assignedTo' => 'assigned_to',
            'title' => 'title',
            'description' => 'description',
            'acceptanceCriteria' => 'acceptance_criteria',
            'status' => 'status',
            'type' => 'type',
            'priority' => 'priority',
            'taskCategory' => 'task_category',
            'dueDate' => 'due_date',
            'expectedResolutionDate' => 'expected_resolution_date',
            'startDate' => 'start_date',
            'sprintNumber' => 'sprint_number',
            'estimatedHours' => 'estimated_hours',
            'internalReviewerNotes' => 'internal_reviewer_notes',
        ];

        foreach ($fieldMap as $camel => $snake) {
            if (array_key_exists($camel, $data)) {
                $updateData[$snake] = $data[$camel];
            }
        }

        $task->update($updateData);

        if (array_key_exists('assignedTo', $data) && ! empty($data['assignedTo'])) {
            $this->ensureAssignedUserExistsOnTask($task, (int) $data['assignedTo'], $user);
        }

        // Log status change
        if (isset($data['status']) && $oldStatus !== $data['status']) {
            TaskActivity::logStatusChange($task, $user, $oldStatus ?? 'none', $data['status']);
            $this->taskNotificationService->sendTaskStatusChanged($task, $user, $oldStatus ?? 'none', $data['status']);
        }

        // Update tags
        if (array_key_exists('tagIds', $data)) {
            $task->tags()->sync($data['tagIds'] ?? []);
        }

        return $task->fresh()->load(['assignee', 'supervisor', 'project', 'parent', 'children', 'tags', 'assignees.user']);
    }

    /**
     * Delete a task.
     */
    public function deleteTask(Task $task): bool
    {
        return $task->delete();
    }

    /**
     * Add an assignee to a task.
     * - Main Tasks: Can add any user as assignee (builds the team)
     * - Weekly/Subtasks: Can only assign users who are already in the Main Task's team
     *
     * @throws \InvalidArgumentException
     */
    public function addAssignee(Task $task, int $userId, string $role, bool $isPrimary, User $performer): TaskAssignee
    {
        // For Weekly/Subtasks, verify user is part of the Main Task's team
        if ($task->type !== TaskType::MAIN) {
            $mainTask = $this->findMainTask($task);
            if ($mainTask) {
                $isInTeam = $mainTask->assignees()->where('user_id', $userId)->exists();
                if (! $isInTeam) {
                    throw new \InvalidArgumentException(
                        'This user is not part of the project team. Add them to the Main Task first.'
                    );
                }
            }
        }

        // Check if user is already assigned to this task
        $existingAssignee = $task->assignees()->where('user_id', $userId)->first();
        if ($existingAssignee) {
            throw new \InvalidArgumentException(
                'This user is already assigned to this task.'
            );
        }

        $assignee = TaskAssignee::create([
            'task_id' => $task->id,
            'user_id' => $userId,
            'role' => $role,
            'is_primary' => $isPrimary,
            'created_by' => $performer->id,
            'updated_by' => $performer->id,
        ]);

        $user = User::find($userId);
        TaskActivity::logAssigneeAdded($task, $performer, $user, $role);

        $this->taskNotificationService->sendTaskAssigned(
            $task->loadMissing(['project', 'assignees.user', 'createdBy']),
            $performer,
            collect([$user])->filter(fn ($assigneeUser) => $assigneeUser instanceof User)
        );

        // Propagate assignee to all child tasks (Weekly and Subtasks)
        $this->propagateAssigneeToChildren($task, $userId, $role, $isPrimary, $performer);

        return $assignee->load('user');
    }

    /**
     * Propagate an assignee to Weekly Tasks only (not Subtasks).
     * When adding to Main Task → propagate to Weekly Tasks only
     * When adding to Weekly Task → do NOT propagate to Subtasks
     */
    private function propagateAssigneeToChildren(Task $task, int $userId, string $role, bool $isPrimary, User $performer): void
    {
        // Only propagate if this is a Main Task
        if ($task->type !== TaskType::MAIN) {
            return;
        }

        // Get only Weekly Tasks (direct children of Main Task)
        $weeklyTasks = $task->children()->where('type', TaskType::WEEKLY)->get();

        foreach ($weeklyTasks as $weeklyTask) {
            // Check if assignee already exists on weekly task
            $existingAssignee = $weeklyTask->assignees()->where('user_id', $userId)->first();

            if (! $existingAssignee) {
                TaskAssignee::create([
                    'task_id' => $weeklyTask->id,
                    'user_id' => $userId,
                    'role' => $role,
                    'is_primary' => $isPrimary,
                    'created_by' => $performer->id,
                    'updated_by' => $performer->id,
                ]);
            }
        }
    }

    /**
     * Remove an assignee from a task.
     * - Can remove from ANY task type (Main/Weekly/Subtask)
     * - Main Tasks: Also removes from Weekly Tasks only (not Subtasks)
     * - Weekly/Subtasks: Only removes from this specific task
     *
     * @throws \InvalidArgumentException
     */
    public function removeAssignee(Task $task, int $userId, User $performer): bool
    {
        $assignee = $task->assignees()->where('user_id', $userId)->first();
        if (! $assignee) {
            return false;
        }

        $user = User::find($userId);
        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $performer->id,
            'type' => TaskActivity::TYPE_ASSIGNEE_REMOVED,
            'description' => "Removed {$user->name} from task",
            'metadata' => ['assignee_id' => $userId, 'assignee_name' => $user->name],
        ]);

        // For Main Tasks, also remove from Weekly Tasks only
        if ($task->type === TaskType::MAIN) {
            $this->removeAssigneeFromWeeklyTasks($task, $userId);
        }

        return $assignee->delete();
    }

    /**
     * Remove an assignee from Weekly Tasks only (not Subtasks).
     */
    private function removeAssigneeFromWeeklyTasks(Task $task, int $userId): void
    {
        $weeklyTasks = $task->children()->where('type', TaskType::WEEKLY)->get();

        foreach ($weeklyTasks as $weeklyTask) {
            $weeklyTask->assignees()->where('user_id', $userId)->delete();
        }
    }

    /**
     * Add a test scenario to a task.
     * Test scenarios can only be added to Weekly Tasks or Subtasks.
     * Main Tasks cannot have test scenarios.
     *
     * @throws \InvalidArgumentException
     */
    public function addTestScenario(Task $task, array $data, User $user): TaskTestScenario
    {
        // Main Tasks cannot have test scenarios
        if ($task->type === TaskType::MAIN) {
            throw new \InvalidArgumentException(
                'Test scenarios can only be added to Weekly Tasks or Subtasks. Main Tasks organize the overall work structure.'
            );
        }

        $maxOrder = $task->testScenarios()->max('order') ?? 0;

        return TaskTestScenario::create([
            'task_id' => $task->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'expected_result' => $data['expectedResult'] ?? null,
            'order' => $maxOrder + 1,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    /**
     * Mark a test scenario as completed by developer.
     */
    public function completeTestScenario(TaskTestScenario $scenario, User $user): TaskTestScenario
    {
        $scenario->markAsCompleted($user);

        TaskActivity::create([
            'task_id' => $scenario->task_id,
            'user_id' => $user->id,
            'type' => TaskActivity::TYPE_TEST_COMPLETED,
            'description' => "Completed test: {$scenario->title}",
            'metadata' => ['scenario_id' => $scenario->id],
        ]);

        return $scenario->fresh();
    }

    /**
     * Verify a test scenario by reviewer.
     */
    public function verifyTestScenario(TaskTestScenario $scenario, User $reviewer, bool $passed, ?string $notes = null): TaskTestScenario
    {
        $scenario->verify($reviewer, $passed, $notes);

        $type = $passed ? TaskActivity::TYPE_TEST_VERIFIED : TaskActivity::TYPE_TEST_FAILED;
        $status = $passed ? 'passed' : 'failed';

        TaskActivity::create([
            'task_id' => $scenario->task_id,
            'user_id' => $reviewer->id,
            'type' => $type,
            'description' => "Test {$status}: {$scenario->title}",
            'metadata' => ['scenario_id' => $scenario->id, 'passed' => $passed],
        ]);

        // Award/deduct points only for primary assignees
        $developer = $scenario->completedBy;
        if ($developer) {
            $assignee = TaskAssignee::where('task_id', $scenario->task_id)
                ->where('user_id', $developer->id)
                ->where('is_primary', true) // Only primary assignees get points
                ->first();

            if ($assignee) {
                $passed ? $assignee->addPoints(1) : $assignee->deductPoints(1);
            }
        }

        return $scenario->fresh();
    }

    /**
     * Add a comment to a task.
     */
    public function addComment(Task $task, User $user, string $comment, bool $isInternal = false): TaskActivity
    {
        $activity = TaskActivity::logComment($task, $user, $comment, $isInternal);
        $this->taskNotificationService->sendTaskCommentAdded($task, $user, $comment, $isInternal);

        return $activity;
    }

    /**
     * Get task activities.
     */
    public function getActivities(Task $task, bool $includeInternal = false, int $limit = 50): Collection
    {
        $query = $task->activities();

        if (! $includeInternal) {
            $query->where('is_internal', false);
        }

        return $query->with('user')->limit($limit)->get();
    }

    /**
     * Get all available tags.
     */
    public function getAllTags(): Collection
    {
        return Tag::ofType('task')->orderBy('name')->get();
    }

    /**
     * Get tasks assigned to a user (via task_assignees).
     */
    public function getTasksForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Task::query()
            ->whereHas('assignees', fn ($q) => $q->where('user_id', $userId))
            ->with(['project', 'parent', 'supervisor', 'tags', 'assignees.user'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get task hierarchy (all ancestors and descendants).
     */
    public function getTaskHierarchy(Task $task): array
    {
        return [
            'task' => $task->load(['assignee', 'supervisor', 'project', 'tags', 'assignees.user']),
            'ancestors' => $task->getAllAncestors()->load(['assignee', 'supervisor']),
            'children' => $this->loadAllDescendants($task),
        ];
    }

    /**
     * Recursively load all descendants of a task.
     */
    private function loadAllDescendants(Task $task): Collection
    {
        $children = $task->children()->with(['assignee', 'supervisor', 'tags', 'assignees.user'])->get();

        foreach ($children as $child) {
            $child->setRelation('children', $this->loadAllDescendants($child));
        }

        return $children;
    }

    /**
     * Create a weekly task under a main task.
     */
    public function createWeeklyTask(Task $mainTask, array $data, User $user): Task
    {
        $data['parentId'] = $mainTask->id;
        $data['projectId'] = $mainTask->project_id;
        $data['type'] = TaskType::WEEKLY->value;

        return $this->createTask($data, $user);
    }

    /**
     * Create a subtask under a weekly task.
     */
    public function createSubtask(Task $weeklyTask, array $data, User $user): Task
    {
        $data['parentId'] = $weeklyTask->id;
        $data['projectId'] = $weeklyTask->project_id;
        $data['type'] = TaskType::SUBTASK->value;

        return $this->createTask($data, $user);
    }

    /**
     * Get user points summary for a project (only from primary assignees).
     * This ensures that reviewers and team leads don't get counted in performance metrics.
     */
    public function getUserPointsSummary(int $projectId): Collection
    {
        return TaskAssignee::query()
            ->whereHas('task', fn ($q) => $q->where('project_id', $projectId))
            ->where('is_primary', true) // Only count points from primary assignments
            ->selectRaw('user_id, SUM(points_earned) as total_points')
            ->groupBy('user_id')
            ->with('user:id,name,email')
            ->get();
    }

    /**
     * Update task status with flexible transitions.
     * Allows changing to any status without strict workflow validation.
     */
    public function updateTaskStatus(Task $task, string $status, User $user, ?string $reason = null): Task
    {
        $oldStatus = $task->status;
        $newStatus = TaskStatus::from($status);

        // Check if status is actually changing
        if ($oldStatus === $newStatus) {
            return $task;
        }

        // Update task status
        $task->update([
            'status' => $newStatus,
            'updated_by' => $user->id,
        ]);

        // Log the status change activity
        TaskActivity::logStatusChange(
            $task,
            $user,
            $oldStatus->value,
            $newStatus->value,
            $reason
        );

        $this->taskNotificationService->sendTaskStatusChanged(
            $task,
            $user,
            $oldStatus->value,
            $newStatus->value,
            $reason
        );

        // Handle special status transitions
        $this->handleStatusTransition($task, $oldStatus, $newStatus, $user);

        return $task->fresh();
    }

    /**
     * Handle special logic for status transitions.
     */
    private function handleStatusTransition(Task $task, TaskStatus $oldStatus, TaskStatus $newStatus, User $user): void
    {
        // Reopen counting is handled centrally by TaskObserver to avoid double increments.
        if ($oldStatus === TaskStatus::TESTING && $newStatus === TaskStatus::IN_PROGRESS) {
            TaskActivity::logComment(
                $task,
                $user,
                'Task reopened from testing - bug bounce detected',
                true // internal comment
            );
        }

        // If moving to completed, set completion timestamp and calculate delay
        if ($newStatus === TaskStatus::COMPLETED && $oldStatus !== TaskStatus::COMPLETED) {
            $completedAt = now();
            $delayDays = null;

            // Calculate delay if due_date is set
            if ($task->due_date) {
                $dueDate = $task->due_date->startOfDay();
                $completedDate = $completedAt->startOfDay();

                if ($completedDate->isAfter($dueDate)) {
                    // Completed after due date = late (positive)
                    $delayDays = $dueDate->diffInDays($completedDate);
                } elseif ($completedDate->isBefore($dueDate)) {
                    // Completed before due date = early (negative)
                    $delayDays = -$completedDate->diffInDays($dueDate);
                } else {
                    // Completed on due date = on time
                    $delayDays = 0;
                }
            }

            $task->update([
                'completed_at' => $completedAt,
                'delay_days' => $delayDays,
            ]);

            // Log completion with delay info
            $delayMessage = '';
            if ($delayDays !== null) {
                if ($delayDays > 0) {
                    $delayMessage = " ({$delayDays} days late)";
                } elseif ($delayDays < 0) {
                    $delayMessage = ' ('.abs($delayDays).' days early)';
                } else {
                    $delayMessage = ' (on time)';
                }
            }

            TaskActivity::logComment(
                $task,
                $user,
                "Task completed{$delayMessage}",
                false // public comment
            );
        }

        // If moving away from completed, clear completion timestamp
        if ($oldStatus === TaskStatus::COMPLETED && $newStatus !== TaskStatus::COMPLETED) {
            $task->update(['completed_at' => null]);
        }
    }

    /**
     * Mark a task as complete with timeline validation.
     * Validates that the user is a primary assignee and no active timers exist.
     */
    public function markAsComplete(Task $task, User $user, ?string $reason = null): Task
    {
        // Check if user is a primary assignee of this task
        $isPrimaryAssignee = $task->assignees()
            ->where('user_id', $user->id)
            ->where('is_primary', true)
            ->exists();

        if (! $isPrimaryAssignee) {
            throw new \InvalidArgumentException('Only primary assignees can mark tasks as complete');
        }

        // Check if task is already completed
        if ($task->status === TaskStatus::COMPLETED) {
            throw new \InvalidArgumentException('Task is already completed');
        }

        // Check for active timers on this task
        $activeTimer = TimeEntry::where('trackable_type', Task::class)
            ->where('trackable_id', $task->id)
            ->where('user_id', $user->id)
            ->whereNull('end_time')
            ->first();

        if ($activeTimer) {
            throw new \InvalidArgumentException('Please stop the active timer before marking task as complete');
        }

        // Update task status to completed
        return $this->updateTaskStatus($task, TaskStatus::COMPLETED->value, $user, $reason);
    }

    private function ensureAssignedUserExistsOnTask(Task $task, int $assignedUserId, User $actor): void
    {
        $existingAssignee = $task->assignees()->where('user_id', $assignedUserId)->first();
        if ($existingAssignee) {
            if (! $existingAssignee->is_primary) {
                $existingAssignee->update([
                    'is_primary' => true,
                    'updated_by' => $actor->id,
                ]);
            }

            return;
        }

        $assignedUser = User::find($assignedUserId);
        if (! $assignedUser) {
            return;
        }

        TaskAssignee::create([
            'task_id' => $task->id,
            'user_id' => $assignedUserId,
            'role' => 'developer',
            'is_primary' => true,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        TaskActivity::logAssigneeAdded($task, $actor, $assignedUser, 'developer');
        $this->taskNotificationService->sendTaskAssigned(
            $task->loadMissing(['project', 'assignees.user', 'createdBy']),
            $actor,
            collect([$assignedUser])
        );
    }
}
