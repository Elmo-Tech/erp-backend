<?php

namespace App\Policies;

use App\Modules\HR\Models\RewardType;
use App\Modules\User\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RewardTypePolicy
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
    public function view(User $user, RewardType $rewardType): bool
    {
        return $user->hasRole(['admin', 'hr_manager', 'manager']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'hr_manager']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RewardType $rewardType): bool
    {
        return $user->hasRole(['admin', 'hr_manager']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RewardType $rewardType): bool
    {
        return $user->hasRole(['admin', 'hr_manager']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, RewardType $rewardType): bool
    {
        return $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, RewardType $rewardType): bool
    {
        return $user->hasRole(['admin']);
    }
}
