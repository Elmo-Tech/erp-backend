<?php

namespace App\Policies;

use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * ProjectPolicy — 100% permission-based, zero role checks.
 *
 * Permission map:
 *   view_projects          → can see the Projects page / list
 *   view_all_projects      → can see every project regardless of membership
 *   manage_all_projects    → can edit/delete any project regardless of membership
 *   create_projects        → can create new projects
 *   edit_projects          → can edit project details (members only, unless manage_all_projects)
 *   delete_projects        → can delete projects
 *   approve_project_ui     → can approve UI deliverables
 *   access_client_portal   → identifies a client-portal user (replaces hasRole('client'))
 */
class ProjectPolicy
{
    use HandlesAuthorization;

    // ─────────────────────────────────────────────────────────────────────────
    // viewAny — can the user reach the projects list at all?
    // ─────────────────────────────────────────────────────────────────────────
    public function viewAny(User $user): bool
    {
        return $user->can('view_projects');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // view — can the user open a specific project?
    //
    // Rules (in priority order):
    //   1. Client-portal users may only see their own client's project.
    //   2. Users with manage_all_projects see every project (global bypass).
    //   3. Everyone else must be a project member.
    // ─────────────────────────────────────────────────────────────────────────
    public function view(User $user, Project $project): bool
    {
        // Client-portal users are scoped to their own client's projects.
        if ($user->can('access_client_portal')) {
            return $project->client_id === $user->client?->id;
        }

        // Unrestricted project visibility (global admin/PMO bypass).
        if ($user->can('manage_all_projects') || $user->can('view_all_projects')) {
            return true;
        }

        // Fall back to membership check.
        return $this->isProjectMember($user, $project);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // create — can the user create a new project?
    // ─────────────────────────────────────────────────────────────────────────
    public function create(User $user): bool
    {
        // Client-portal users may never create projects.
        if ($user->can('access_client_portal')) {
            return false;
        }

        return $user->can('create_projects');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // update — can the user edit a specific project?
    //
    // Rules:
    //   1. Client-portal users cannot update projects.
    //   2. manage_all_projects → unrestricted update access.
    //   3. edit_projects + project membership → scoped update access.
    // ─────────────────────────────────────────────────────────────────────────
    public function update(User $user, Project $project): bool
    {
        if ($user->can('access_client_portal')) {
            return false;
        }

        if ($user->can('manage_all_projects')) {
            return true;
        }

        return $user->can('edit_projects') && $this->isProjectMember($user, $project);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // delete — can the user delete a project?
    // ─────────────────────────────────────────────────────────────────────────
    public function delete(User $user, Project $project): bool
    {
        if ($user->can('access_client_portal')) {
            return false;
        }

        if ($user->can('manage_all_projects')) {
            return true;
        }

        return $user->can('delete_projects') && $this->isProjectMember($user, $project);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // approveUI — can the user approve UI deliverables?
    //
    // Rules:
    //   1. Client-portal users may approve UI for their own client's project.
    //   2. Internal users with approve_project_ui permission may also approve.
    // ─────────────────────────────────────────────────────────────────────────
    public function approveUI(User $user, Project $project): bool
    {
        if ($user->can('access_client_portal')) {
            return $project->client_id === $user->client?->id;
        }

        return $user->can('approve_project_ui');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────────────────
    protected function isProjectMember(User $user, Project $project): bool
    {
        return $project->members()->where('user_id', $user->id)->exists();
    }
}
