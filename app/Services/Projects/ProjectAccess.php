<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class ProjectAccess
{
    public function canViewAny(User $user): bool
    {
        return $user->can('view_all_projects')
            || $user->client()->exists()
            || $user->projectMemberships()->exists();
    }

    public function canView(User $user, Project $project): bool
    {
        if ($user->can('view_all_projects')) {
            return true;
        }

        if ($user->client?->id === $project->client_id) {
            return true;
        }

        return $project->members()
            ->where('user_id', $user->id)
            ->exists();
    }

    public function canCreate(User $user): bool
    {
        return $user->can('create_projects') || $user->can('manage_projects');
    }

    public function canUpdate(User $user, Project $project): bool
    {
        if ($user->can('manage_all_projects')) {
            return true;
        }

        return ($user->can('edit_projects') || $user->can('manage_projects'))
            && $this->canView($user, $project)
            && $user->client?->id !== $project->client_id;
    }

    public function canDelete(User $user, Project $project): bool
    {
        return ($user->can('delete_projects') || $user->can('manage_projects'))
            && $this->canUpdate($user, $project);
    }

    public function canViewTasks(User $user, Project $project): bool
    {
        return ($user->can('view_tasks') || $user->can('manage_tasks'))
            && $this->canView($user, $project);
    }

    public function canCreatePipelineTasks(User $user, Project $project): bool
    {
        return ($user->can('create_main_tasks') || $user->can('manage_tasks'))
            && $this->canView($user, $project);
    }

    public function canAssignPipelineTasks(User $user, Project $project): bool
    {
        return ($user->can('assign_tasks') || $user->can('manage_tasks'))
            && $this->canView($user, $project);
    }

    public function canMovePipelineTasks(User $user, Project $project): bool
    {
        return ($user->can('edit_main_tasks') || $user->can('manage_tasks'))
            && $this->canView($user, $project);
    }

    public function canDeletePipelineTasks(User $user, Project $project): bool
    {
        return ($user->can('delete_tasks') || $user->can('manage_tasks'))
            && $this->canView($user, $project);
    }

    public function canManagePipelineTaskCover(User $user, Project $project, Task $task): bool
    {
        if (! $this->canView($user, $project)) {
            return false;
        }

        if ($user->can('edit_main_tasks') || $user->can('manage_tasks')) {
            return true;
        }

        return $user->can('create_main_tasks')
            && $task->getAttribute('created_by') === $user->id;
    }

    public function canManageWorkflow(User $user, Project $project): bool
    {
        return $user->can('manage_project_workflows')
            && $this->canUpdate($user, $project);
    }
}
