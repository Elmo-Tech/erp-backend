<?php

namespace App\Modules\Settings\Policies;

use App\Modules\Settings\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class UserAdministrationPolicy
{
    private const FOUNDER_REQUIRED_PERMISSIONS = [
        'manage_roles',
        'manage_protected_users',
    ];

    private const ADMINISTRATIVE_PERMISSIONS = [
        'manage_users',
        'edit_users',
        'toggle_user_status',
        'manage_roles',
        'create_roles',
        'edit_roles',
        'delete_roles',
        'assign_user_roles',
        'reset_user_credentials',
        'delete_users',
        'protected_user_account',
        'manage_employees',
        'create_employees',
        'edit_employees',
        'delete_employees',
    ];

    public function authorizeCreate(User $actor): void
    {
        abort_unless($actor->hasAnyPermission(['create_users', 'manage_users']), 403);
    }

    public function authorizeUpdate(User $actor, User $target): void
    {
        abort_unless($actor->hasAnyPermission(['edit_users', 'manage_users']), 403);
        $this->authorizeManageTarget($actor, $target);
    }

    public function authorizeManageTarget(User $actor, User $target): void
    {
        abort_if($actor->is($target) || $this->userTier($actor) <= $this->userTier($target), 403);
    }

    public function authorizePasswordReset(User $actor, User $target): void
    {
        abort_unless($actor->can('reset_user_credentials'), 403);
        $this->authorizeManageTarget($actor, $target);
    }

    public function authorizeStatusToggle(User $actor, User $target): void
    {
        abort_unless($actor->can('toggle_user_status'), 403);
        $this->authorizeManageTarget($actor, $target);
    }

    public function authorizeEmployeeMutation(User $actor, User $target, string $permission): void
    {
        abort_unless($actor->hasAnyPermission([$permission, 'manage_employees']), 403);
        $this->authorizeManageTarget($actor, $target);
    }

    public function authorizeDelete(User $actor, User $target): void
    {
        abort_unless($actor->can('delete_users'), 403);
        $this->authorizeManageTarget($actor, $target);
    }

    public function authorizeRoleAssignment(User $actor, ?User $target, array $roleNames): void
    {
        abort_unless($actor->can('assign_user_roles'), 403);

        if ($target !== null) {
            $this->authorizeManageTarget($actor, $target);
        }

        $roles = Role::query()->with('permissions')->whereIn('name', $roleNames)->get();
        abort_if($roles->count() !== count(array_unique($roleNames)), 422);

        $requestedTier = $roles->map(fn (Role $role): int => $this->roleTier($role))->max() ?? 1;
        $actorTier = $this->userTier($actor);
        abort_if($actorTier <= $requestedTier, 403);

        if ($actorTier < 3) {
            $this->authorizePermissionSubset($actor, $roles);
        }
    }

    public function authorizeRoleCreation(User $actor, array $permissionNames): void
    {
        abort_unless($actor->hasAnyPermission(['create_roles', 'manage_roles']), 403);
        $this->authorizePermissionGrant($actor, $permissionNames);
    }

    public function authorizeRoleMutation(User $actor, Role $role, array $permissionNames): void
    {
        abort_unless($actor->hasAnyPermission(['edit_roles', 'manage_roles']), 403);
        abort_if($this->userTier($actor) <= $this->roleTier($role->loadMissing('permissions')), 403);
        $this->authorizePermissionGrant($actor, $permissionNames);
    }

    public function authorizeProtectedRolePermissionMutation(
        User $actor,
        Role $role,
        array $permissionNames
    ): void {
        abort_unless(
            $actor->hasAnyPermission(['edit_roles', 'manage_roles'])
                && $actor->can('manage_protected_users'),
            403
        );

        if ($role->name === 'founder') {
            abort_if(
                array_diff(self::FOUNDER_REQUIRED_PERMISSIONS, $permissionNames) !== [],
                403,
                'The founder role must retain its critical administration permissions.'
            );

            return;
        }

        $this->authorizePermissionGrant($actor, $permissionNames);
    }

    public function authorizeRoleDeletion(User $actor, Role $role): void
    {
        abort_unless($actor->hasAnyPermission(['delete_roles', 'manage_roles']), 403);
        abort_if($this->userTier($actor) <= $this->roleTier($role->loadMissing('permissions')), 403);
    }

    private function userTier(User $user): int
    {
        if ($user->can('manage_protected_users')) {
            return 3;
        }

        return $user->hasAnyPermission(self::ADMINISTRATIVE_PERMISSIONS) ? 2 : 1;
    }

    private function roleTier(Role $role): int
    {
        $permissions = $role->permissions->pluck('name');

        if ($permissions->contains('manage_protected_users')) {
            return 3;
        }

        return $permissions->intersect(self::ADMINISTRATIVE_PERMISSIONS)->isNotEmpty() ? 2 : 1;
    }

    private function authorizePermissionSubset(User $actor, Collection $roles): void
    {
        $actorPermissions = $actor->getAllPermissions()->pluck('name');
        $requestedPermissions = $roles->flatMap(
            fn (Role $role): Collection => $role->permissions->pluck('name')
        )->unique();

        abort_if($requestedPermissions->diff($actorPermissions)->isNotEmpty(), 403);
    }

    private function authorizePermissionGrant(User $actor, array $permissionNames): void
    {
        $requestedTier = in_array('manage_protected_users', $permissionNames, true)
            ? 3
            : (array_intersect($permissionNames, self::ADMINISTRATIVE_PERMISSIONS) !== [] ? 2 : 1);
        $actorTier = $this->userTier($actor);

        abort_if($actorTier <= $requestedTier, 403);

        if ($actorTier < 3) {
            $actorPermissions = $actor->getAllPermissions()->pluck('name');
            abort_if(collect($permissionNames)->diff($actorPermissions)->isNotEmpty(), 403);
        }
    }
}
