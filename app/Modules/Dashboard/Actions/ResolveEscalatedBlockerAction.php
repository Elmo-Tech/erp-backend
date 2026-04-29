<?php

namespace App\Modules\Dashboard\Actions;

use App\Modules\Task\Models\TaskDependency;

class ResolveEscalatedBlockerAction
{
    public function execute(TaskDependency $dependency): TaskDependency
    {
        $dependency->forceFill([
            'is_escalated' => false,
            'resolved_at' => now(),
        ])->save();

        return $dependency->refresh()->load(['task.project', 'task.assignee', 'dependsOnTask', 'escalatedBy']);
    }
}
