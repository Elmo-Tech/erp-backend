<?php

namespace App\Modules\Dashboard\Actions;

use App\Enums\TaskStatus;
use App\Modules\PersonalTasks\Models\PersonalTask;
use App\Modules\Task\Models\Task;
use App\Modules\Settings\Models\User;
use Illuminate\Support\Arr;

class GetPersonalTasksAction
{
    public function execute(User $user): array
    {
        $assignedSummary = Task::query()
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

        $personalSummary = PersonalTask::query()
            ->where('user_id', $user->id)
            ->selectRaw(
                'COUNT(*) as total,
                COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as todo,
                COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as in_progress,
                COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as completed',
                ['todo', 'in_progress', 'done']
            )
            ->first();

        $assignedItems = Task::query()
            ->with('project:id,title')
            ->where('assigned_to', $user->id)
            ->where('status', '!=', TaskStatus::COMPLETED->value)
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['id', 'project_id', 'title', 'status', 'priority', 'due_date', 'updated_at'])
            ->map(fn (Task $task): array => [
                'task_id' => $task->id,
                'source' => 'project',
                'title' => $task->title,
                'status' => $this->scalar($task->status),
                'priority' => $this->scalar($task->priority),
                'due_date' => $task->due_date?->toDateString(),
                'project' => $task->project?->title,
                'sort_due_date' => $task->due_date?->toDateString() ?? '9999-12-31',
                'sort_updated_at' => $task->updated_at?->timestamp ?? 0,
            ]);

        $personalItems = PersonalTask::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', 'done')
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['id', 'title', 'status', 'due_date', 'updated_at'])
            ->map(fn (PersonalTask $task): array => [
                'task_id' => $task->id,
                'source' => 'personal',
                'title' => $task->title,
                'status' => $task->status,
                'priority' => null,
                'due_date' => $task->due_date?->toDateString(),
                'project' => 'Personal',
                'sort_due_date' => $task->due_date?->toDateString() ?? '9999-12-31',
                'sort_updated_at' => $task->updated_at?->timestamp ?? 0,
            ]);

        $items = $assignedItems
            ->merge($personalItems)
            ->sortBy([
                ['sort_due_date', 'asc'],
                ['sort_updated_at', 'desc'],
            ])
            ->take(10)
            ->map(fn (array $item): array => Arr::except($item, ['sort_due_date', 'sort_updated_at']))
            ->values()
            ->all();

        return [
            'summary' => [
                'total' => (int) $assignedSummary->total + (int) $personalSummary->total,
                'todo' => (int) $assignedSummary->todo + (int) $personalSummary->todo,
                'in_progress' => (int) $assignedSummary->in_progress + (int) $personalSummary->in_progress,
                'review' => (int) $assignedSummary->review,
                'testing' => (int) $assignedSummary->testing,
                'completed' => (int) $assignedSummary->completed + (int) $personalSummary->completed,
            ],
            'items' => $items,
        ];
    }

    private function scalar(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
