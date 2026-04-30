<?php

namespace App\Modules\Dashboard\Actions;

use App\Enums\TaskStatus;
use App\Modules\Task\Models\Task;
use App\Modules\Settings\Models\User;

class GetPersonalTasksAction
{
    public function execute(User $user): array
    {
        $summary = Task::query()
            ->where('assigned_to', $user->id)
            ->selectRaw(
                'COUNT(*) as total,
                COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as todo,
                COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as in_progress,
                COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as review,
                COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as testing,
                COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as completed',
                [
                    TaskStatus::TODO->value,
                    TaskStatus::IN_PROGRESS->value,
                    TaskStatus::REVIEW->value,
                    TaskStatus::TESTING->value,
                    TaskStatus::COMPLETED->value,
                ]
            )
            ->first();

        $items = Task::query()
            ->with('project:id,title')
            ->where('assigned_to', $user->id)
            ->where('status', '!=', TaskStatus::COMPLETED->value)
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['id', 'project_id', 'title', 'status', 'priority', 'due_date', 'updated_at'])
            ->map(fn (Task $task): array => [
                'task_id' => $task->id,
                'title' => $task->title,
                'status' => $this->scalar($task->status),
                'priority' => $this->scalar($task->priority),
                'due_date' => $task->due_date?->toDateString(),
                'project' => $task->project?->title,
            ])
            ->all();

        return [
            'summary' => [
                'total' => (int) $summary->total,
                'todo' => (int) $summary->todo,
                'in_progress' => (int) $summary->in_progress,
                'review' => (int) $summary->review,
                'testing' => (int) $summary->testing,
                'completed' => (int) $summary->completed,
            ],
            'items' => $items,
        ];
    }

    private function scalar(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
