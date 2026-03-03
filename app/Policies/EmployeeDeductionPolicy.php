<?php

namespace App\Policies;

use App\Models\User;
use App\Models\EmployeeDeduction;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeeDeductionPolicy
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
    public function view(User $user, EmployeeDeduction $employeeDeduction): bool
    {
        // User can view their own deductions
        if ($user->id === $employeeDeduction->user_id) {
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
    public function update(User $user, EmployeeDeduction $employeeDeduction): bool
    {
        return $user->hasRole(['admin', 'hr_manager']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EmployeeDeduction $employeeDeduction): bool
    {
        return $user->hasRole(['admin', 'hr_manager']);
    }

    /**
     * Determine whether the user can approve the model.
     */
    public function approve(User $user, EmployeeDeduction $employeeDeduction): bool
    {
        // Cannot approve own deduction
        if ($user->id === $employeeDeduction->user_id) {
            return false;
        }
        
        return $user->hasRole(['admin', 'hr_manager']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EmployeeDeduction $employeeDeduction): bool
    {
        return $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EmployeeDeduction $employeeDeduction): bool
    {
        return $user->hasRole(['admin']);
    }
}
