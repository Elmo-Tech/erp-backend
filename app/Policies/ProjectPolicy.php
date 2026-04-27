<?php

namespace App\Policies;

use App\Modules\Project\Models\Project;
use App\Modules\User\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any projects.
     */
    public function viewAny(User $user): bool
    {
        // Clients can view their own projects (filtered by ClientScope)
        // Admins/employees can view projects they're members of (or all if they have permission)
        return true;
    }

    /**
     * Determine whether the user can view the project.
     */
    public function view(User $user, Project $project): bool
    {
        // Clients can only view their own projects
        if ($user->hasRole('client')) {
            return $project->client_id === $user->client?->id;
        }

        // Super Admin and Admin can view all projects
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        // Users with view_all_projects permission can view all
        if ($user->can('view_all_projects')) {
            return true;
        }

        // Employees can only view projects they are members of
        return $this->isProjectMember($user, $project);
    }

    /**
     * Check if user is a member of the project
     */
    protected function isProjectMember(User $user, Project $project): bool
    {
        return $project->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can create projects.
     */
    public function create(User $user): bool
    {
        // Clients cannot create projects
        return ! $user->hasRole('client');
    }

    /**
     * Determine whether the user can update the project.
     */
    public function update(User $user, Project $project): bool
    {
        // Clients cannot update projects
        if ($user->hasRole('client')) {
            return false;
        }

        // Super Admin and Admin can update all projects
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        // Users with manage_all_projects permission can update all
        if ($user->can('manage_all_projects')) {
            return true;
        }

        // Employees can only update projects they are members of
        return $this->isProjectMember($user, $project);
    }

    /**
     * Determine whether the user can delete the project.
     */
    public function delete(User $user, Project $project): bool
    {
        // Only admins can delete projects
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    /**
     * Determine whether the user can approve UI deliverables.
     */
    public function approveUI(User $user, Project $project): bool
    {
        // Only the project's client can approve UI
        if ($user->hasRole('client')) {
            return $project->client_id === $user->client?->id;
        }

        // Admins can also approve on behalf of client
        return $user->hasAnyRole(['admin', 'super-admin']);
    }
}
