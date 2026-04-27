<?php

namespace App\Policies;

use App\Enums\TaskType;
use App\Modules\Task\Models\Task;
use App\Modules\User\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * TaskPolicy handles authorization for task operations.
 *
 * Requirements: 4.2
 *
 * Team & Test Scenario Permissions:
 * - Main/Weekly Tasks: Only users with manage_task_team/manage_task_test_scenarios permission
 * - Subtasks: Any assigned user can manage team (view only) and test scenarios
 */
class TaskPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any tasks.
     */
    public function viewAny(User $user): bool
    {
        // Clients cannot view tasks (they see project progress only)
        if ($user->hasRole('client')) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'super-admin', 'employee']);
    }

    /**
     * Determine whether the user can view the task.
     */
    public function view(User $user, Task $task): bool
    {
        // Clients cannot view individual tasks
        if ($user->hasRole('client')) {
            return false;
        }

        // Admins and super-admins can view all tasks
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        // Employees can view tasks they are assigned to or tasks in their projects
        if ($user->hasRole('employee')) {
            // Can view if assigned to this task
            if ($task->assigned_to === $user->id) {
                return true;
            }

            // Can view if created by this user
            if ($task->created_by === $user->id) {
                return true;
            }

            // Allow viewing all tasks for now (can be restricted later)
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create tasks.
     */
    public function create(User $user): bool
    {
        // Clients cannot create tasks
        if ($user->hasRole('client')) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'super-admin', 'employee']);
    }

    /**
     * Determine whether the user can update the task.
     */
    public function update(User $user, Task $task): bool
    {
        // Clients cannot update tasks
        if ($user->hasRole('client')) {
            return false;
        }

        // Admins and super-admins can update all tasks
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        // Employees can update tasks they are assigned to or created
        if ($user->hasRole('employee')) {
            return $task->assigned_to === $user->id || $task->created_by === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the task.
     */
    public function delete(User $user, Task $task): bool
    {
        // Clients cannot delete tasks
        if ($user->hasRole('client')) {
            return false;
        }

        // Only admins and super-admins can delete tasks
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    /**
     * Determine whether the user can view internal notes.
     *
     * Requirements: 4.2 - Only users with view_internal_notes permission
     */
    public function viewInternalNotes(User $user, Task $task): bool
    {
        return $user->can('view_internal_notes');
    }

    /**
     * Determine whether the user can update internal notes.
     */
    public function updateInternalNotes(User $user, Task $task): bool
    {
        // Must have permission to view internal notes
        if (! $user->can('view_internal_notes')) {
            return false;
        }

        // Admins and super-admins can always update
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        // Employees with permission can update if they are assigned or created the task
        if ($user->hasRole('employee')) {
            return $task->assigned_to === $user->id || $task->created_by === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can assign the task to someone.
     */
    public function assign(User $user, Task $task): bool
    {
        // Clients cannot assign tasks
        if ($user->hasRole('client')) {
            return false;
        }

        // Admins and super-admins can assign any task
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        // Employees can only assign tasks they created
        if ($user->hasRole('employee')) {
            return $task->created_by === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can change the task status.
     */
    public function changeStatus(User $user, Task $task): bool
    {
        // Clients cannot change task status
        if ($user->hasRole('client')) {
            return false;
        }

        // Admins and super-admins can change any task status
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        // Employees can change status of tasks they are assigned to
        if ($user->hasRole('employee')) {
            return $task->assigned_to === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can manage team members (assignees) on a task.
     *
     * Rules:
     * - All task types (Main/Weekly/Subtask): Admin/Super Admin or users with manage_task_team permission can add/remove members
     * - When adding to Main Task: propagates to Weekly Tasks only
     * - When removing from Main Task: removes from Weekly Tasks only
     * - Weekly/Subtasks: can be managed independently
     */
    public function manageTeam(User $user, Task $task): bool
    {
        // Clients cannot manage team
        if ($user->hasRole('client')) {
            return false;
        }

        // Admins and super-admins can always manage team
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        // For all task types, require manage_task_team permission
        return $user->can('manage_task_team');
    }

    /**
     * Determine whether the user can view team members on a task.
     * Everyone who can view the task can view the team.
     */
    public function viewTeam(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }

    /**
     * Determine whether the user can manage test scenarios on a task.
     *
     * Rules:
     * - Main Tasks: Cannot have test scenarios (business rule)
     * - Weekly Tasks: Only Admin/Super Admin or users with manage_task_test_scenarios permission
     * - Subtasks: Any assigned user or creator can manage test scenarios
     */
    public function manageTestScenarios(User $user, Task $task): bool
    {
        // Clients cannot manage test scenarios
        if ($user->hasRole('client')) {
            return false;
        }

        // Main Tasks cannot have test scenarios
        if ($task->type === TaskType::MAIN) {
            return false;
        }

        // Admins and super-admins can always manage test scenarios
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        // For Weekly tasks, require specific permission
        if ($task->type === TaskType::WEEKLY) {
            return $user->can('manage_task_test_scenarios');
        }

        // For Subtasks, assigned users or creator can manage test scenarios
        if ($task->type === TaskType::SUBTASK) {
            // Check if user is assigned to this task
            $isAssigned = $task->assignees()->where('user_id', $user->id)->exists();

            return $isAssigned || $task->assigned_to === $user->id || $task->created_by === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can view test scenarios on a task.
     * Everyone who can view the task can view test scenarios.
     */
    public function viewTestScenarios(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }
}
