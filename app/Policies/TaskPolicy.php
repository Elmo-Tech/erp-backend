<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * TaskPolicy handles authorization for task operations.
 *
 * Requirements: 4.2
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
        if (!$user->can('view_internal_notes')) {
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
}
