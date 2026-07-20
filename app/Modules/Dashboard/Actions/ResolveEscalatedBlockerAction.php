<?php

namespace App\Modules\Dashboard\Actions;

use App\Modules\Settings\Models\User;
use App\Modules\Task\Models\TaskDependency;

class ResolveEscalatedBlockerAction
{
    public function execute(TaskDependency $dependency, User $resolvedBy): TaskDependency
    {
        $dependency->forceFill([
            'is_escalated' => false,
            'resolved_at' => now(),
            'resolved_by' => $resolvedBy->id,
        ])->save();

        return $dependency->refresh()->load(['task.project', 'task.assignee', 'dependsOnTask', 'escalatedBy', 'resolvedBy']);
    }
}
