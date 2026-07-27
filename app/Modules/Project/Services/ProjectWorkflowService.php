<?php

namespace App\Modules\Project\Services;

use App\Modules\Project\Enums\ExecutionTaskStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Project\Models\ProjectTimeEntry;
use App\Modules\Project\Models\ProjectWorkflowStage;
use App\Modules\Project\Models\WorkflowTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectWorkflowService
{
    private const DEFAULT_STAGES = [
        ['name' => 'Backlog', 'status' => 'backlog', 'color' => '#64748b', 'is_terminal' => false],
        ['name' => 'Ready', 'status' => 'to_do', 'color' => '#3b82f6', 'is_terminal' => false],
        ['name' => 'In Progress', 'status' => 'in_progress', 'color' => '#f59e0b', 'is_terminal' => false],
        ['name' => 'On Hold', 'status' => 'on_hold', 'color' => '#f97316', 'is_terminal' => false],
        ['name' => 'In Review', 'status' => 'in_review', 'color' => '#8b5cf6', 'is_terminal' => false],
        ['name' => 'QA', 'status' => 'qa', 'color' => '#06b6d4', 'is_terminal' => false],
        ['name' => 'Done', 'status' => 'done', 'color' => '#22c55e', 'is_terminal' => true],
        ['name' => 'Cancelled', 'status' => 'cancelled', 'color' => '#ef4444', 'is_terminal' => true],
    ];

    /** @return Collection<int, ProjectWorkflowStage> */
    public function ensureProjectWorkflow(Project $project): Collection
    {
        if ($project->workflowStages()->exists()) {
            return $project->workflowStages()->get();
        }

        return DB::transaction(function () use ($project): Collection {
            if ($project->workflowStages()->lockForUpdate()->exists()) {
                return $project->workflowStages()->get();
            }

            foreach (self::DEFAULT_STAGES as $position => $stage) {
                $project->workflowStages()->create([...$stage, 'position' => $position]);
            }

            return $project->workflowStages()->get();
        });
    }

    public function stageForStatus(Project $project, ExecutionTaskStatus $status): ProjectWorkflowStage
    {
        $this->ensureProjectWorkflow($project);

        return $project->workflowStages()
            ->where('status', $status->value)
            ->orderBy('position')
            ->firstOrFail();
    }

    public function syncTaskStage(ProjectTask $task): ProjectTask
    {
        $task->loadMissing('project');

        if ($task->workflowStage?->status === $task->status) {
            return $task;
        }

        $stage = $this->stageForStatus($task->project, $task->status);
        $task->forceFill(['workflow_stage_id' => $stage->id])->save();

        return $task->refresh();
    }

    public function moveTask(ProjectTask $task, ProjectWorkflowStage $stage, int $position = 0): ProjectTask
    {
        if ($task->project_id !== $stage->project_id) {
            throw ValidationException::withMessages([
                'workflowStageId' => ['The selected workflow stage does not belong to this project.'],
            ]);
        }

        return DB::transaction(function () use ($task, $stage, $position): ProjectTask {
            $sourceStageId = $task->workflow_stage_id;
            $task->update([
                'workflow_stage_id' => $stage->id,
                'status' => $stage->status,
            ]);
            $this->reorderStageTasks($task, $stage->id, $position);

            if ($sourceStageId && $sourceStageId !== $stage->id) {
                $this->compactStagePositions($task->project_id, $sourceStageId);
            }

            if ($stage->is_terminal || in_array($stage->status, [
                ExecutionTaskStatus::ON_HOLD,
                ExecutionTaskStatus::DONE,
                ExecutionTaskStatus::CANCELLED,
            ], true)) {
                $this->stopTaskTimers($task);
            }

            return $task->refresh();
        });
    }

    public function moveTaskToStatus(ProjectTask $task, ExecutionTaskStatus $status): ProjectTask
    {
        $task->loadMissing('project');

        return $this->moveTask($task, $this->stageForStatus($task->project, $status));
    }

    /**
     * @param  array<int, array<string, mixed>>  $stages
     * @return Collection<int, ProjectWorkflowStage>
     */
    public function replaceProjectStages(Project $project, array $stages): Collection
    {
        $this->validateRequiredStatuses($stages);

        return DB::transaction(function () use ($project, $stages): Collection {
            $existing = $project->workflowStages()->get()->keyBy('id');
            $retainedIds = [];
            $project->workflowStages()->reorder()->increment('position', 1000);

            foreach ($stages as $position => $stageData) {
                $stage = isset($stageData['id']) ? $existing->get((int) $stageData['id']) : null;
                $attributes = $this->stageAttributes($stageData, $position);

                if ($stage) {
                    $previousStatus = $stage->status;
                    $wasTerminal = $stage->is_terminal;
                    $stage->update($attributes);

                    if ($previousStatus !== $stage->status) {
                        $stage->tasks()->update(['status' => $stage->status->value]);
                    }

                    if (
                        ($previousStatus !== $stage->status
                            && ($stage->status === ExecutionTaskStatus::ON_HOLD || $stage->is_terminal))
                        || ($stage->is_terminal && ! $wasTerminal)
                    ) {
                        $stage->tasks()->get()->each($this->stopTaskTimers(...));
                    }
                } else {
                    $stage = $project->workflowStages()->create($attributes);
                }

                $retainedIds[] = $stage->id;
            }

            $removed = $project->workflowStages()->whereNotIn('id', $retainedIds)->get();
            if ($removed->contains(fn (ProjectWorkflowStage $stage): bool => $stage->tasks()->exists())) {
                throw ValidationException::withMessages([
                    'stages' => ['Move tasks out of a stage before deleting it.'],
                ]);
            }

            $project->workflowStages()->whereNotIn('id', $retainedIds)->delete();

            return $project->workflowStages()->get();
        });
    }

    /** @return Collection<int, ProjectWorkflowStage> */
    public function applyTemplate(Project $project, WorkflowTemplate $template): Collection
    {
        $template->loadMissing('stages');
        $this->validateRequiredStatuses(
            $template->stages->map(fn ($stage): array => ['status' => $stage->status->value])->all()
        );

        return DB::transaction(function () use ($project, $template): Collection {
            $project->executionTasks()->update(['workflow_stage_id' => null]);
            $project->workflowStages()->delete();

            foreach ($template->stages as $stage) {
                $project->workflowStages()->create([
                    'name' => $stage->name,
                    'status' => $stage->status,
                    'color' => $stage->color,
                    'position' => $stage->position,
                    'is_terminal' => $stage->is_terminal,
                ]);
            }

            $project->executionTasks()->with('project')->get()->each(
                fn (ProjectTask $task): ProjectTask => $this->syncTaskStage($task)
            );

            return $project->workflowStages()->get();
        });
    }

    public function stopTaskTimers(ProjectTask $task): void
    {
        ProjectTimeEntry::query()
            ->where('task_id', $task->id)
            ->whereNull('end_time')
            ->lockForUpdate()
            ->get()
            ->each(fn (ProjectTimeEntry $entry) => $entry->stop());
    }

    /** @param array<string, mixed> $stage */
    private function stageAttributes(array $stage, int $position): array
    {
        return [
            'name' => $stage['name'],
            'status' => $stage['status'],
            'color' => $stage['color'],
            'position' => $position,
            'is_terminal' => (bool) ($stage['isTerminal'] ?? false),
        ];
    }

    private function reorderStageTasks(ProjectTask $movedTask, int $stageId, int $position): void
    {
        $tasks = ProjectTask::query()
            ->where('project_id', $movedTask->project_id)
            ->where('workflow_stage_id', $stageId)
            ->whereKeyNot($movedTask->id)
            ->orderBy('pipeline_position')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $tasks->splice(min(max(0, $position), $tasks->count()), 0, [$movedTask]);

        $tasks->values()->each(
            fn (ProjectTask $task, int $taskPosition) => $task->forceFill([
                'pipeline_position' => $taskPosition,
            ])->save()
        );
    }

    private function compactStagePositions(int $projectId, int $stageId): void
    {
        ProjectTask::query()
            ->where('project_id', $projectId)
            ->where('workflow_stage_id', $stageId)
            ->orderBy('pipeline_position')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->values()
            ->each(
                fn (ProjectTask $task, int $position) => $task->forceFill([
                    'pipeline_position' => $position,
                ])->save()
            );
    }

    /** @param array<int, array<string, mixed>> $stages */
    public function validateRequiredStatuses(array $stages): void
    {
        $statuses = collect($stages)->pluck('status')->map(
            fn ($status): string => $status instanceof ExecutionTaskStatus ? $status->value : (string) $status
        );
        $missing = collect(ExecutionTaskStatus::getAllStatuses())
            ->map(fn (ExecutionTaskStatus $status): string => $status->value)
            ->diff($statuses);

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'stages' => ['Workflow is missing required states: '.$missing->implode(', ').'.'],
            ]);
        }
    }
}
