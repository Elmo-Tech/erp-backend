<?php

namespace App\Services\Tasks;

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActiveWorkService
{
    /** Include open records even when their task/project is archived or missing. */
    public function openTimers(bool $onlyWorkTasks = true): Collection
    {
        return TimeEntry::with([
            'user:id,name',
            'trackable' => fn (MorphTo $target) => $target
                ->constrain([Task::class => fn ($task) => $task->withTrashed()])
                ->morphWith([Task::class => ['project' => fn ($project) => $project->withoutGlobalScopes()->withTrashed()]]),
        ])->whereNull('end_time')
            ->when($onlyWorkTasks, fn ($query) => $query->where('trackable_type', Task::class))
            ->orderBy('start_time')->orderBy('id')->get();
    }

    public function target(TimeEntry $entry): array
    {
        if ($entry->trackable_type !== Task::class) {
            return ['task' => null, 'project' => null, 'status' => 'available', 'message' => null];
        }
        $task = $entry->relationLoaded('trackable') ? $entry->getRelation('trackable') : null;
        $task ??= Task::withTrashed()->find($entry->trackable_id);
        if (! $task instanceof Task) {
            return ['task' => null, 'project' => null, 'status' => 'task_deleted', 'message' => 'This task is no longer available. You can stop its timer to save the recorded work time.'];
        }

        return $this->taskTarget($task);
    }

    public function taskTarget(Task $task): array
    {
        $project = $task->relationLoaded('project') ? $task->getRelation('project') : null;
        if ($project === null && $task->project_id !== null) {
            $project = Project::withoutGlobalScopes()->withTrashed()->find($task->project_id);
        }
        $result = ['task' => $task, 'project' => $project, 'status' => 'available', 'message' => null];
        if ($task->trashed()) {
            return [...$result, 'status' => 'task_deleted', 'message' => 'This task was deleted. Restore it before resuming work; its timer can still be stopped.'];
        }
        if ($task->project_id !== null && ! $project instanceof Project) {
            return [...$result, 'status' => 'project_deleted', 'message' => 'The project for this task is no longer available. Its timer can still be stopped.'];
        }
        if ($project?->trashed()) {
            return [...$result, 'status' => 'project_archived', 'message' => 'This project is in Deleted projects. Restore it before resuming work; its timer can still be stopped.'];
        }

        return $result;
    }
}
