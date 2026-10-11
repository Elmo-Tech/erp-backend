<?php

namespace App\Services\Projects;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\TimerStopReason;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectWorkflowStage;
use App\Models\Task;
use App\Models\TaskAssignee;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Services\Sales\TimeTrackingService;
use App\Services\Tasks\TaskSupervisionService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProjectWorkflowService
{
    public function __construct(
        private TaskSupervisionService $supervision,
        private ?TimeTrackingService $timeTracking = null,
    ) {
        $this->timeTracking ??= app(TimeTrackingService::class);
    }

    public function ensureProjectWorkflow(Project $project, ?WorkflowTemplate $template = null): Collection
    {
        if ($project->workflowStages()->exists()) {
            return $project->workflowStages()->get();
        }

        $template ??= WorkflowTemplate::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->with('stages')
            ->firstOrFail();

        $template->loadMissing('stages');

        DB::transaction(function () use ($project, $template): void {
            Project::withoutGlobalScopes()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            if (ProjectWorkflowStage::query()->where('project_id', $project->id)->exists()) {
                return;
            }

            foreach ($template->stages as $stage) {
                $project->workflowStages()->create([
                    'workflow_template_stage_id' => $stage->id,
                    'name' => $stage->name,
                    'slug' => $stage->slug,
                    'color' => $stage->color,
                    'category' => $stage->category,
                    'position' => $stage->position,
                    'is_terminal' => $stage->is_terminal,
                ]);
            }
        });

        return $project->workflowStages()->get();
    }

    public function getPipeline(Project $project): array
    {
        $this->ensureProjectWorkflow($project);

        $stages = $project->workflowStages()
            ->with(['tasks' => fn ($query) => $query->with([
                'assignee', 'assignees.user', 'workflowStage', 'supervisor',
                'timeEntries' => fn ($entries) => $entries->whereNull('end_time')->with('user'),
            ])])
            ->get();

        return [
            'project' => [
                'projectId' => $project->id,
                'title' => $project->title,
            ],
            'stages' => $stages->map(fn (ProjectWorkflowStage $stage) => $this->stagePayload($stage, true))->all(),
        ];
    }

    public function createTask(Project $project, User $actor, array $data): Task
    {
        $stage = $project->workflowStages()->findOrFail($data['workflowStageId']);
        $data['assignedUserIds'] = $this->normalizedAssigneeIds($data);
        $this->validateAssignees($data['assignedUserIds']);

        return DB::transaction(
            fn (): Task => $this->persistTask($project, $stage, $actor, $data)
        );
    }

    private function persistTask(
        Project $project,
        ProjectWorkflowStage $stage,
        User $actor,
        array $data
    ): Task {
        ProjectWorkflowStage::query()->whereKey($stage->id)->lockForUpdate()->firstOrFail();
        $position = ((int) $stage->tasks()->max('pipeline_position')) + 1;
        $assigneeIds = $data['assignedUserIds'];

        $this->addAssigneesToProject($project, $assigneeIds);

        $task = Task::query()->create([
            'project_id' => $project->id,
            'workflow_stage_id' => $stage->id,
            'is_pipeline_task' => true,
            'pipeline_position' => $position,
            'parent_id' => null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'assigned_to' => $assigneeIds[0] ?? null,
            'due_date' => $data['dueDate'] ?? null,
            'estimated_hours' => $data['estimatedHours'] ?? null,
            'priority' => $data['priority'] ?? TaskPriority::MEDIUM->value,
            'type' => TaskType::MAIN,
            'status' => $this->legacyStatusFor($stage),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $this->createTaskAssignees($task, $actor, $assigneeIds);

        if (isset($data['supervisorId'])) {
            $this->assignPipelineSupervisor($project, $task, $actor, $data['supervisorId']);
        }

        return $task->load(['assignee', 'assignees.user', 'workflowStage']);
    }

    private function createTaskAssignees(Task $task, User $actor, array $userIds): void
    {
        foreach ($userIds as $index => $userId) {
            $task->assignees()->create([
                'user_id' => $userId,
                'role' => 'developer',
                'is_primary' => $index === 0,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        }
    }

    public function moveTask(
        Project $project,
        Task $task,
        ProjectWorkflowStage $stage,
        User $actor,
        ?int $position = null
    ): Task {
        if (
            ! $task->is_pipeline_task
            || $task->project_id !== $project->id
            || $stage->project_id !== $project->id
        ) {
            abort(404);
        }

        DB::transaction(function () use ($task, $stage, $actor, $position): void {
            $targetPosition = $position ?? (((int) $stage->tasks()->max('pipeline_position')) + 1);

            $stage->tasks()
                ->where('pipeline_position', '>=', $targetPosition)
                ->increment('pipeline_position');

            $task->update([
                'workflow_stage_id' => $stage->id,
                'pipeline_position' => $targetPosition,
                'status' => $this->legacyStatusFor($stage),
                'hold_reason' => $stage->category === 'paused' ? ($task->hold_reason ?: 'Moved to On Hold') : null,
                'completed_at' => $stage->category === 'done' ? now() : null,
                'updated_by' => $actor->id,
            ]);

            // A finished task must not keep a running timer behind.
            if ($stage->category === 'done') {
                $this->timeTracking->stopTimersForTrackable($task, TimerStopReason::END);
            }
        });

        return $task->fresh()->load(['assignee', 'assignees.user', 'workflowStage']);
    }

    public function updateTask(Project $project, Task $task, User $actor, array $data): Task
    {
        $this->ensurePipelineTaskBelongsToProject($project, $task);
        $stage = array_key_exists('workflowStageId', $data)
            ? $project->workflowStages()->findOrFail($data['workflowStageId'])
            : null;
        $changesAssignees = array_key_exists('assignedTo', $data)
            || array_key_exists('assignedUserIds', $data);
        $assigneeIds = $changesAssignees ? $this->normalizedAssigneeIds($data) : [];

        if ($changesAssignees) {
            $this->validateAssignees($assigneeIds);
        }

        DB::transaction(function () use ($project, $task, $actor, $data, $stage, $changesAssignees, $assigneeIds): void {
            Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $updates = ['updated_by' => $actor->id];

            foreach ([
                'title' => 'title',
                'description' => 'description',
                'dueDate' => 'due_date',
                'estimatedHours' => 'estimated_hours',
                'priority' => 'priority',
            ] as $input => $column) {
                if (array_key_exists($input, $data)) {
                    $updates[$column] = $data[$input];
                }
            }

            if ($stage instanceof ProjectWorkflowStage) {
                $updates['workflow_stage_id'] = $stage->id;
                $updates['status'] = $this->legacyStatusFor($stage);
                $updates['hold_reason'] = $stage->category === 'paused'
                    ? ($task->hold_reason ?: 'Moved to On Hold')
                    : null;
                $updates['completed_at'] = $stage->category === 'done' ? now() : null;
            }

            if ($changesAssignees) {
                $this->addAssigneesToProject($project, $assigneeIds);
                $updates['assigned_to'] = $assigneeIds[0] ?? null;
            }

            $task->update($updates);

            if ($stage instanceof ProjectWorkflowStage && $stage->category === 'done') {
                $this->timeTracking->stopTimersForTrackable($task, TimerStopReason::END);
            }

            if ($changesAssignees) {
                $task->assignees()->delete();
                $this->createTaskAssignees($task, $actor, $assigneeIds);
            }

            if (array_key_exists('supervisorId', $data)) {
                $this->assignPipelineSupervisor($project, $task, $actor, $data['supervisorId']);
            }
        });

        return $task->fresh()->load(['assignee', 'assignees.user', 'workflowStage']);
    }

    public function deleteTask(Project $project, Task $task): void
    {
        $this->ensurePipelineTaskBelongsToProject($project, $task);
        $this->timeTracking->stopTimersForTrackable($task, TimerStopReason::END);
        $this->deleteCoverImageFile($task);
        $task->delete();
    }

    public function replaceTaskCover(Project $project, Task $task, string $coverImagePath): Task
    {
        $this->ensurePipelineTaskBelongsToProject($project, $task);
        $previousCoverImagePath = $task->cover_image_path;

        $task->update(['cover_image_path' => $coverImagePath]);

        if ($previousCoverImagePath && $previousCoverImagePath !== $coverImagePath) {
            Storage::disk('public')->delete($previousCoverImagePath);
        }

        return $task->fresh()->load(['assignee', 'assignees.user', 'workflowStage']);
    }

    public function removeTaskCover(Project $project, Task $task): Task
    {
        $this->ensurePipelineTaskBelongsToProject($project, $task);
        $this->deleteCoverImageFile($task);
        $task->update(['cover_image_path' => null]);

        return $task->fresh()->load(['assignee', 'assignees.user', 'workflowStage']);
    }

    public function replaceProjectStages(Project $project, array $stages): Collection
    {
        return DB::transaction(function () use ($project, $stages): Collection {
            Project::withoutGlobalScopes()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            $project->workflowStages()->update([
                'position' => DB::raw('position + 100'),
            ]);

            $retainedIds = [];
            foreach ($stages as $position => $data) {
                $stage = isset($data['stageId'])
                    ? $project->workflowStages()->findOrFail($data['stageId'])
                    : new ProjectWorkflowStage(['project_id' => $project->id]);

                $stage->fill([
                    'name' => $data['name'],
                    'slug' => $data['slug'],
                    'color' => $data['color'],
                    'category' => $data['category'],
                    'position' => $position,
                    'is_terminal' => (bool) ($data['isTerminal'] ?? false),
                ])->save();

                $retainedIds[] = $stage->id;
            }

            $removedStages = $project->workflowStages()
                ->whereNotIn('id', $retainedIds)
                ->withCount('tasks')
                ->get();

            if ($removedStages->contains(fn (ProjectWorkflowStage $stage) => $stage->tasks_count > 0)) {
                throw ValidationException::withMessages([
                    'stages' => ['Move tasks out of a stage before removing it.'],
                ]);
            }

            $project->workflowStages()
                ->whereNotIn('id', $retainedIds)
                ->delete();

            return $project->workflowStages()->get();
        });
    }

    public function applyTemplate(Project $project, WorkflowTemplate $template): Collection
    {
        if ($project->pipelineTasks()->exists()) {
            throw ValidationException::withMessages([
                'templateId' => ['A template cannot replace a workflow after pipeline tasks have been created.'],
            ]);
        }

        return DB::transaction(function () use ($project, $template): Collection {
            $project->workflowStages()->delete();

            return $this->ensureProjectWorkflow($project, $template);
        });
    }

    public function stagePayload(ProjectWorkflowStage $stage, bool $withTasks = false): array
    {
        $payload = [
            'stageId' => $stage->id,
            'name' => $stage->name,
            'slug' => $stage->slug,
            'color' => $stage->color,
            'category' => $stage->category,
            'position' => $stage->position,
            'isTerminal' => $stage->is_terminal,
        ];

        if ($withTasks) {
            $payload['tasks'] = $stage->tasks
                ->map(fn (Task $task) => $this->taskPayload($task))
                ->all();
        }

        return $payload;
    }

    public function taskPayload(Task $task): array
    {
        $task->loadMissing([
            'assignees.user', 'supervisor',
            'timeEntries' => fn ($entries) => $entries->whereNull('end_time')->with('user'),
        ]);
        $assignee = $task->assignee;
        $dueDate = $task->due_date;

        return [
            'taskId' => $task->id,
            'projectId' => $task->project_id,
            'workflowStageId' => $task->workflow_stage_id,
            'title' => $task->title,
            'description' => $task->description,
            'coverImageUrl' => $task->cover_image_url,
            'priority' => $task->priorityValue(),
            'assignedTo' => $task->assigned_to,
            'supervisorId' => $task->supervisor_id,
            'supervisor' => $task->supervisor instanceof User ? [
                'userId' => $task->supervisor->id,
                'name' => $task->supervisor->name,
                'avatar' => $task->supervisor->avatar,
            ] : null,
            'totalTimeSpent' => (int) $task->total_time_spent,
            'activeWorkTimers' => $task->timeEntries
                ->filter(fn ($entry) => $entry->end_time === null)
                ->map(fn ($entry) => [
                    'userId' => $entry->user_id,
                    'name' => $entry->user?->name ?? 'Unknown user',
                    'startTime' => $entry->start_time->toIso8601String(),
                ])
                ->values()->all(),
            'assignee' => $assignee instanceof User ? [
                'userId' => $assignee->id,
                'name' => $assignee->name,
                'avatar' => $assignee->avatar,
            ] : null,
            'assignees' => $task->assignees
                ->map(fn (TaskAssignee $taskAssignee) => [
                    'userId' => $taskAssignee->user->id,
                    'name' => $taskAssignee->user->name,
                    'avatar' => $taskAssignee->user->avatar,
                    'isPrimary' => $taskAssignee->is_primary,
                ])
                ->values()
                ->all(),
            'dueDate' => $dueDate ? Carbon::parse($dueDate)->format('Y-m-d') : null,
            'estimatedHours' => $task->estimated_hours !== null ? (float) $task->estimated_hours : null,
            'position' => $task->pipeline_position,
            'holdReason' => $task->hold_reason,
        ];
    }

    private function assignPipelineSupervisor(Project $project, Task $task, User $actor, ?int $supervisorId): void
    {
        if ($supervisorId !== null) {
            $candidate = User::query()->whereKey($supervisorId)->where('is_active', true)->first();
            $isMember = $project->members()->where('user_id', $supervisorId)->exists();

            if ($candidate === null || (! $candidate->is_employee && ! $isMember)) {
                throw ValidationException::withMessages([
                    'supervisorId' => ['The supervisor must be an active employee or project member.'],
                ]);
            }

            $this->addAssigneesToProject($project, [$supervisorId]);
        }

        $this->supervision->setSupervisor($task, $supervisorId, $actor);
        $task->refresh();
    }

    private function normalizedAssigneeIds(array $data): array
    {
        $ids = $data['assignedUserIds'] ?? [];

        if ($ids === [] && filled($data['assignedTo'] ?? null)) {
            $ids = [$data['assignedTo']];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function validateAssignees(array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        $activeEmployeeCount = User::query()
            ->employees()
            ->where('is_active', true)
            ->whereKey($userIds)
            ->count();

        if ($activeEmployeeCount !== count($userIds)) {
            throw ValidationException::withMessages([
                'assignedUserIds' => ['Every assignee must be an active employee.'],
            ]);
        }
    }

    private function addAssigneesToProject(Project $project, array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        $now = now();
        ProjectMember::query()->insertOrIgnore(
            array_map(fn (int $userId) => [
                'project_id' => $project->id,
                'user_id' => $userId,
                'role' => 'member',
                'created_at' => $now,
                'updated_at' => $now,
            ], $userIds)
        );
    }

    private function ensurePipelineTaskBelongsToProject(Project $project, Task $task): void
    {
        if (! $task->is_pipeline_task || $task->project_id !== $project->id) {
            abort(404);
        }
    }

    private function deleteCoverImageFile(Task $task): void
    {
        if ($task->cover_image_path) {
            Storage::disk('public')->delete($task->cover_image_path);
        }
    }

    private function legacyStatusFor(ProjectWorkflowStage $stage): TaskStatus
    {
        return match ($stage->category) {
            'active' => TaskStatus::IN_PROGRESS,
            'paused' => TaskStatus::ON_HOLD,
            'review' => TaskStatus::REVIEW,
            'done' => TaskStatus::COMPLETED,
            default => TaskStatus::TODO,
        };
    }
}
