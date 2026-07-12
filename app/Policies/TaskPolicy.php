<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Settings\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * TaskPolicy — Strict ABAC/PBAC for project execution tasks.
 *
 * CRITICAL SECURITY RULES:
 *   1. manage_all_projects → unrestricted bypass for ALL actions.
 *   2. Task updates/execution → ONLY the exact assignee (assignee_id === user.id)
 *      OR a manager-level user (manage_tasks, edit_tasks, or project manager/tech_lead).
 *   3. Deliverable management → ONLY users with manage_deliverables permission.
 *   4. NO "protected role" restrictions. PMOs CAN be assigned tasks.
 *
 * Permission matrix:
 *   manage_all_projects  → unrestricted access to every action.
 *   manage_tasks         → can create, edit, delete tasks in any visible project.
 *   create_tasks         → can create tasks in any visible project.
 *   edit_tasks           → can edit task metadata in any visible project.
 *   work_on_tasks        → can update status/deliverables on tasks where assignee_id === user.id.
 *   delete_tasks         → can delete tasks if project manager/tech_lead OR manage_all_projects.
 *   manage_deliverables  → can add/edit/delete deliverables on any task.
 */
class TaskPolicy
{
    use HandlesAuthorization;

    // ─────────────────────────────────────────────────────────────────────────
    // viewAny — can the user see the task list for a project?
    // ─────────────────────────────────────────────────────────────────────────
    public function viewAny(User $user, Project $project): bool
    {
        return $user->can('view_tasks');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // view — can the user open a specific task?
    // ─────────────────────────────────────────────────────────────────────────
    public function view(User $user, ProjectTask $task): bool
    {
        return $user->can('view_tasks');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // create — can the user add tasks/milestones to a project?
    //
    // Rules:
    //   1. manage_all_projects OR manage_tasks → always allowed.
    //   2. create_tasks → allowed after the parent project visibility check.
    // ─────────────────────────────────────────────────────────────────────────
    public function create(User $user, Project $project): bool
    {
        if ($user->can('manage_all_projects') || $user->can('manage_tasks')) {
            return true;
        }

        return $user->can('create_tasks');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // update — can the user edit a task's metadata (title, description, status)?
    //
    // STRICT RULES:
    //   1. manage_all_projects OR manage_tasks → always allowed.
    //   2. edit_tasks → allowed after the parent project visibility check.
    //   3. work_on_tasks + task.assignee_id === user.id → allowed (own task only).
    //   4. Everyone else → DENIED.
    //
    // CRITICAL: Only the EXACT assignee can update their own task. No one else.
    // ─────────────────────────────────────────────────────────────────────────
    public function update(User $user, ProjectTask $task): bool
    {
        if ($user->can('manage_all_projects') || $user->can('manage_tasks')) {
            return true;
        }

        if ($user->can('edit_tasks')) {
            return true;
        }

        // STRICT: Assignee can update their own task (status transitions, etc.)
        // This is the ONLY way a developer/QA can update a task.
        if ($user->can('work_on_tasks') && (int) $task->assignee_id === $user->id) {
            return true;
        }

        return false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // delete — can the user delete a task?
    //
    // Rules:
    //   1. manage_all_projects OR manage_tasks → always allowed.
    //   2. delete_tasks + project membership role of manager/tech_lead → allowed.
    //   3. Everyone else → DENIED.
    // ─────────────────────────────────────────────────────────────────────────
    public function delete(User $user, ProjectTask|Project $task): bool
    {
        if ($task instanceof Project) {
            return $user->can('manage_all_projects') || $user->can('delete_milestones');
        }

        if ($user->can('manage_all_projects')) {
            return true;
        }

        if ($user->can('delete_tasks')) {
            return true;
        }

        return ($user->can('manage_tasks') || $user->can('edit_tasks')) && $this->isProjectManager($user, $task->project);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // execute — can the user submit deliverables / update task execution state?
    //
    // STRICT: ONLY the explicitly assigned user (assignee_id) may execute a task.
    // Managers and global admins bypass this restriction.
    //
    // CRITICAL: This enforces that developers/QA can ONLY work on tasks that
    // are explicitly assigned to them. No one else can submit work on their behalf.
    // ─────────────────────────────────────────────────────────────────────────
    public function execute(User $user, ProjectTask $task): bool
    {
        if ($user->can('manage_all_projects') || $user->can('manage_tasks')) {
            return true;
        }

        // Project managers/tech leads can also execute tasks
        if ($user->can('edit_tasks') && $this->isProjectManager($user, $task->project)) {
            return true;
        }

        // STRICT: Only the assigned user may execute
        return (int) $task->assignee_id === $user->id;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // complete — can the user mark a task as DONE?
    //
    // Developers cannot call /complete directly — their ceiling is in_review.
    // QA, managers, tech leads, and global admins may complete tasks.
    // ─────────────────────────────────────────────────────────────────────────
    public function complete(User $user, ProjectTask $task): bool
    {
        if ($user->can('manage_all_projects') || $user->can('manage_tasks')) {
            return true;
        }

        $project = $task->project;
        $member  = $project->members()->where('user_id', $user->id)->first();
        $role    = $member?->role instanceof ProjectRole ? $member->role->value : (string) ($member?->role ?? '');

        // Developers cannot complete tasks directly
        if ($role === ProjectRole::DEVELOPER->value) {
            return false;
        }

        // QA, manager, tech_lead, designer with edit_tasks permission
        return $user->can('edit_tasks');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns true if the user holds a manager-level role in the project.
     * Manager-level roles: manager, tech_lead.
     */
    protected function isProjectManager(User $user, ?Project $project): bool
    {
        if ($project === null) {
            return false;
        }

        $member = $project->members()->where('user_id', $user->id)->first();

        if ($member === null) {
            return false;
        }

        $role = $member->role instanceof ProjectRole ? $member->role->value : (string) $member->role;

        return in_array($role, [
            ProjectRole::MANAGER->value,
            ProjectRole::TECH_LEAD->value,
        ], true);
    }
}
