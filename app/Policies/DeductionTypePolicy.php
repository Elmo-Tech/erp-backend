<?php

namespace App\Policies;

use App\Models\DeductionType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DeductionTypePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('manage_deductions') || $user->can('manage_hr_settings') || $user->can('manage_employees');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DeductionType $deductionType): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DeductionType $deductionType): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DeductionType $deductionType): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DeductionType $deductionType): bool
    {
        return $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DeductionType $deductionType): bool
    {
        return $user->hasRole(['admin']);
    }
}
