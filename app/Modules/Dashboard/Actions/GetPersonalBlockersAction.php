<?php

namespace App\Modules\Dashboard\Actions;

use App\Enums\TaskStatus;
use App\Modules\Settings\Models\User;
use App\Modules\Task\Models\Task;
use App\Modules\Task\Models\TaskDependency;
use Illuminate\Support\Collection;

class GetPersonalBlockersAction
{
    public function execute(User $user): array
    {
        return $this->buildQuery()
            ->whereHas('task', fn ($query) => $query->where('assigned_to', $user->id))
            ->get()
            ->pipe(fn (Collection $dependencies): array => $this->format($dependencies));
    }

    public function executeForAll(): array
    {
        return $this->buildQuery()
            ->limit(25)
            ->get()
            ->pipe(fn (Collection $dependencies): array => $this->format($dependencies));
    }

    private function buildQuery()
    {
        return TaskDependency::query()
            ->with([
                'task:id,project_id,assigned_to,title,status,updated_at',
                'task.project:id,title',
                'task.assignee:id,name,email,avatar',
                'dependsOnTask:id,title,status',
                'escalatedBy:id,name,email,avatar',
            ])
            ->whereHas('task', fn ($query) => $query->where('status', '!=', TaskStatus::COMPLETED->value))
            ->whereHas('dependsOnTask', fn ($query) => $query->where('status', '!=', TaskStatus::COMPLETED->value))
            ->latest('updated_at');
    }

    private function format(Collection $dependencies): array
    {
        return $dependencies
            ->groupBy('task_id')
            ->map(function (Collection $taskDependencies): array {
                /** @var TaskDependency $firstDependency */
                $firstDependency = $taskDependencies->first();
                /** @var Task $task */
                $task = $firstDependency->task;
                $escalatedDependency = $taskDependencies->first(fn (TaskDependency $dependency): bool => (bool) $dependency->is_escalated);

                return [
                    'task_id' => $task->id,
                    'dependency_id' => $escalatedDependency?->id ?? $firstDependency->id,
                    'title' => $task->title,
                    'status' => $this->scalar($task->status),
                    'project' => $task->project?->title,
                    'assignee' => $task->assignee ? [
                        'id' => $task->assignee->id,
                        'name' => $task->assignee->name,
                        'email' => $task->assignee->email,
                        'avatar_url' => $task->assignee->avatar ?: null,
                    ] : null,
                    'is_escalated' => (bool) $escalatedDependency,
                    'escalated_at' => $escalatedDependency?->escalated_at?->toISOString(),
                    'escalated_by' => $escalatedDependency?->escalatedBy ? [
                        'id' => $escalatedDependency->escalatedBy->id,
                        'name' => $escalatedDependency->escalatedBy->name,
                        'email' => $escalatedDependency->escalatedBy->email,
                        'avatar_url' => $escalatedDependency->escalatedBy->avatar ?: null,
                    ] : null,
                    'blocked_by' => $taskDependencies
                        ->map(fn (TaskDependency $dependency): array => [
                            'dependency_id' => $dependency->id,
                            'task_id' => $dependency->dependsOnTask?->id,
                            'title' => $dependency->dependsOnTask?->title,
                            'status' => $this->scalar($dependency->dependsOnTask?->status),
                            'is_escalated' => (bool) $dependency->is_escalated,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->take(10)
            ->all();
    }

    private function scalar(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
