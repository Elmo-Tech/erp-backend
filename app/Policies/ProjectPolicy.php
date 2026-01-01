<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
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
        // Admins/employees can view all
        return true;
    }

    /**
     * Determine whether the user can view the project.
     */
    public function view(User $user, Project $project): bool
    {
        if ($user->hasRole('client')) {
            return $project->client_id === $user->id;
        }

        return $user->hasAnyRole(['admin', 'super-admin', 'employee']);
    }

    /**
     * Determine whether the user can create projects.
     */
    public function create(User $user): bool
    {
        // Clients cannot create projects
        return !$user->hasRole('client');
    }

    /**
     * Determine whether the user can update the project.
     */
    public function update(User $user, Project $project): bool
    {
        // Clients cannot update projects
        return !$user->hasRole('client');
    }

    /**
     * Determine whether the user can delete the project.
     */
    public function delete(User $user, Project $project): bool
    {
        // Clients cannot delete projects
        return !$user->hasRole('client');
    }

    /**
     * Determine whether the user can approve UI deliverables.
     */
    public function approveUI(User $user, Project $project): bool
    {
        // Only the project's client can approve UI
        if ($user->hasRole('client')) {
            return $project->client_id === $user->id;
        }

        // Admins can also approve on behalf of client
        return $user->hasAnyRole(['admin', 'super-admin']);
    }
}