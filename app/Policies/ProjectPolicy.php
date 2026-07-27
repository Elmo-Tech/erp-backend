<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Services\Projects\ProjectAccess;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectPolicy
{
    use HandlesAuthorization;

    private ProjectAccess $access;

    public function __construct(?ProjectAccess $access = null)
    {
        $this->access = $access ?? app(ProjectAccess::class);
    }

    /**
     * Determine whether the user can view any projects.
     */
    public function viewAny(User $user): bool
    {
        return $this->access->canViewAny($user);
    }

    /**
     * Determine whether the user can view the project.
     */
    public function view(User $user, Project $project): bool
    {
        return $this->access->canView($user, $project);
    }

    /**
     * Determine whether the user can create projects.
     */
    public function create(User $user): bool
    {
        return $this->access->canCreate($user);
    }

    /**
     * Determine whether the user can update the project.
     */
    public function update(User $user, Project $project): bool
    {
        return $this->access->canUpdate($user, $project);
    }

    /**
     * Determine whether the user can delete the project.
     */
    public function delete(User $user, Project $project): bool
    {
        return $this->access->canDelete($user, $project);
    }

    /**
     * Determine whether the user can approve UI deliverables.
     */
    public function approveUI(User $user, Project $project): bool
    {
        if (config('simplification.enabled')) {
            return false;
        }

        if ($user->client()->exists()) {
            return $project->client_id === $user->client?->id;
        }

        return $user->can('approve_project_ui')
            && $this->access->canView($user, $project);
    }
}
