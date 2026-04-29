<?php

namespace App\Modules\Dashboard\Actions;

use App\Modules\Task\Models\TaskDependency;

class GetEscalatedBlockersAction
{
    public function execute(): array
    {
        return TaskDependency::query()
            ->with(['task.project', 'task.assignee', 'dependsOnTask', 'escalatedBy'])
            ->where('is_escalated', true)
            ->whereNull('resolved_at')
            ->latest('escalated_at')
            ->limit(10)
            ->get()
            ->map(fn (TaskDependency $dependency): array => $this->format($dependency))
            ->all();
    }

    public function format(TaskDependency $dependency): array
    {
        return [
            'id' => $dependency->id,
            'dependency_id' => $dependency->id,
            'task_id' => $dependency->task_id,
            'title' => $dependency->task?->title,
            'status' => $this->scalar($dependency->task?->status),
            'project' => $dependency->task?->project?->title,
            'assignee' => $dependency->task?->assignee ? [
                'id' => $dependency->task->assignee->id,
                'name' => $dependency->task->assignee->name,
                'email' => $dependency->task->assignee->email,
                'avatar_url' => $dependency->task->assignee->avatar ?: null,
            ] : null,
            'blocked_by' => [
                'task_id' => $dependency->depends_on_task_id,
                'title' => $dependency->dependsOnTask?->title,
                'status' => $this->scalar($dependency->dependsOnTask?->status),
            ],
            'escalated_by' => $dependency->escalatedBy ? [
                'id' => $dependency->escalatedBy->id,
                'name' => $dependency->escalatedBy->name,
                'email' => $dependency->escalatedBy->email,
                'avatar_url' => $dependency->escalatedBy->avatar ?: null,
            ] : null,
            'escalated_at' => $dependency->escalated_at?->toISOString(),
        ];
    }

    private function scalar(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
