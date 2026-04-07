<?php

namespace App\Policies;

use App\Models\EmployeeReward;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeeRewardPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'hr_manager', 'manager']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EmployeeReward $employeeReward): bool
    {
        // User can view their own rewards
        if ($user->id === $employeeReward->user_id) {
            return true;
        }

        return $user->hasRole(['admin', 'hr_manager', 'manager']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'hr_manager', 'manager']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EmployeeReward $employeeReward): bool
    {
        return $user->hasRole(['admin', 'hr_manager']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EmployeeReward $employeeReward): bool
    {
        return $user->hasRole(['admin', 'hr_manager']);
    }

    /**
     * Determine whether the user can approve the model.
     */
    public function approve(User $user, EmployeeReward $employeeReward): bool
    {
        // Cannot approve own reward
        if ($user->id === $employeeReward->user_id) {
            return false;
        }

        return $user->hasRole(['admin', 'hr_manager']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EmployeeReward $employeeReward): bool
    {
        return $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EmployeeReward $employeeReward): bool
    {
        return $user->hasRole(['admin']);
    }
}
