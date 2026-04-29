<?php

namespace App\Modules\Dashboard\Actions;

use App\Enums\TaskStatus;
use App\Modules\Task\Models\Task;
use App\Modules\User\Models\User;

class GetPersonalBlockersAction
{
    public function execute(User $user): array
    {
        return Task::query()
            ->with([
                'project:id,title',
                'blockingTasks' => fn ($query) => $query
                    ->select('tasks.id', 'tasks.title', 'tasks.status')
                    ->where('tasks.status', '!=', TaskStatus::COMPLETED->value),
            ])
            ->where('assigned_to', $user->id)
            ->where('status', '!=', TaskStatus::COMPLETED->value)
            ->whereHas('blockingTasks', fn ($query) => $query->where('tasks.status', '!=', TaskStatus::COMPLETED->value))
            ->latest('updated_at')
            ->limit(10)
            ->get(['id', 'project_id', 'title', 'status', 'updated_at'])
            ->map(fn (Task $task): array => [
                'task_id' => $task->id,
                'title' => $task->title,
                'status' => $this->scalar($task->status),
                'project' => $task->project?->title,
                'blocked_by' => $task->blockingTasks
                    ->map(fn (Task $blockingTask): array => [
                        'task_id' => $blockingTask->id,
                        'title' => $blockingTask->title,
                        'status' => $this->scalar($blockingTask->status),
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    private function scalar(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
