<?php

namespace App\Modules\Dashboard\Actions;

use App\Enums\TaskStatus;
use App\Modules\Task\Models\Task;
use App\Modules\Task\Models\TaskDependency;
use App\Modules\Settings\Models\User;

class EscalateBlockerAction
{
    public function execute(Task $task, User $escalatedBy): TaskDependency
    {
        $dependency = TaskDependency::query()
            ->where('task_id', $task->id)
            ->whereHas('task', fn ($query) => $query->where('status', '!=', TaskStatus::COMPLETED->value))
            ->whereHas('dependsOnTask', fn ($query) => $query->where('status', '!=', TaskStatus::COMPLETED->value))
            ->oldest('created_at')
            ->firstOrFail();

        $dependency->forceFill([
            'is_escalated' => true,
            'escalated_by' => $escalatedBy->id,
            'escalated_at' => $dependency->escalated_at ?? now(),
            'resolved_at' => null,
        ])->save();

        return $dependency->refresh()->load(['task.project', 'task.assignee', 'dependsOnTask', 'escalatedBy']);
    }
}
