<?php

namespace App\Services\Tasks;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Projects\ProjectAccess;

/** Shared task visibility rules; project access remains a separate boundary. */
class TaskVisibility
{
    public function canViewAll(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']) || $user->can('manage_tasks') || $user->can('view_all_tasks');
    }

    public function canList(User $user): bool
    {
        return ! $user->client()->exists() && ($this->canViewAll($user) || $user->can('view_tasks'));
    }

    public function scope($query, User $user)
    {
        if ($this->canViewAll($user)) return $query;
        return $query->where(function ($query) use ($user): void {
            $query->where('tasks.assigned_to', $user->id)
                ->orWhere('tasks.supervisor_id', $user->id)->orWhere('tasks.secondary_supervisor_id', $user->id)
                ->orWhereHas('assignees', fn ($assignees) => $assignees->where('user_id', $user->id));
        });
    }

    public function accessibleIds(User $user)
    {
        $query = Task::query()->select('tasks.id');
        if (! $this->canList($user)) return $query->whereRaw('1 = 0');
        $this->scope($query, $user);
        return $query->where(function ($query) use ($user): void {
            $query->whereHas('project')->orWhere(function ($standalone) use ($user): void {
                $standalone->whereNull('tasks.project_id');
                if (! $user->can('view_all_projects')) {
                    $standalone->where(fn ($q) => $q->where('tasks.assigned_to', $user->id)->orWhere('tasks.supervisor_id', $user->id)->orWhere('tasks.secondary_supervisor_id', $user->id)
                        ->orWhereHas('assignees', fn ($a) => $a->where('user_id', $user->id)));
                }
            });
        });
    }

    public function canView(User $user, Task $task): bool
    {
        if (! $this->canList($user)) return false;
        if ($task->project_id !== null) {
            $project = Project::withoutGlobalScope(\App\Scopes\TeamMemberScope::class)->find($task->project_id);
            if (! $project || ! app(ProjectAccess::class)->canView($user, $project)) return false;
        }
        $assigned = (int) $task->assigned_to === $user->id || $task->isSupervisor($user)
            || $task->assignees()->where('user_id', $user->id)->exists();
        if ($task->project_id === null && ! $user->can('view_all_projects')) return $assigned;
        return $this->canViewAll($user) || $assigned;
    }
}
