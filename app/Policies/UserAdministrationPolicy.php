<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class UserAdministrationPolicy
{
    public const PROTECTED_ROLE_NAMES = ['admin', 'client', 'super-admin'];

    private const SUPER_ADMIN_REQUIRED_PERMISSIONS = [
        'manage_users',
        'manage_roles',
        'assign_user_roles',
        'manage_protected_users',
    ];

    private const ADMINISTRATIVE_PERMISSIONS = [
        'manage_users',
        'view_users',
        'create_users',
        'edit_users',
        'delete_users',
        'assign_user_roles',
        'manage_roles',
        'view_roles',
        'create_roles',
        'edit_roles',
        'delete_roles',
        'manage_employees',
    ];

    public function authorizeCreate(User $actor): void
    {
        abort_unless($actor->can('manage_users'), 403);
    }

    public function authorizeManageTarget(User $actor, User $target): void
    {
        abort_if($actor->is($target), 403);

        $actorTier = $this->userTier($actor);
        $targetTier = $this->userTier($target);

        abort_if($actorTier < $targetTier, 403);
        abort_if($actorTier === $targetTier && $actorTier < 3, 403);
    }

    public function authorizeRoleAssignment(User $actor, ?User $target, array $roleNames): void
    {
        abort_unless($actor->can('manage_users') && $actor->can('assign_user_roles'), 403);

        if ($target !== null) {
            $this->authorizeManageTarget($actor, $target);
        }

        $roles = Role::query()
            ->with('permissions')
            ->whereIn('name', array_unique($roleNames))
            ->get();

        abort_if($roles->count() !== count(array_unique($roleNames)), 422);

        $requestedTier = $roles
            ->map(fn (Role $role): int => $this->roleTier($role))
            ->max() ?? 1;
        $actorTier = $this->userTier($actor);

        abort_if($actorTier < $requestedTier, 403);
        abort_if($actorTier === $requestedTier && $actorTier < 3, 403);

        if ($actorTier < 3) {
            $this->authorizePermissionSubset($actor, $roles);
        }
    }

    public function authorizeStatusToggle(User $actor, User $target): void
    {
        abort_unless($actor->can('manage_users'), 403);
        $this->authorizeManageTarget($actor, $target);

        if ($target->can('manage_protected_users') && $target->is_active) {
            abort_if($this->activeSuperAdministratorCount() <= 1, 422);
        }
    }

    public function authorizeEmployeeMutation(User $actor, User $target): void
    {
        abort_unless($actor->can('manage_employees'), 403);
        $this->authorizeManageTarget($actor, $target);
    }

    public function authorizeEmployeeRoleAssignment(User $actor, ?User $target, string $roleName): void
    {
        abort_unless($actor->can('manage_employees'), 403);

        if ($target !== null) {
            $this->authorizeManageTarget($actor, $target);
        }

        $role = Role::query()->with('permissions')->where('name', $roleName)->firstOrFail();
        $actorTier = $this->userTier($actor);
        $requestedTier = $this->roleTier($role);

        abort_if($actorTier < $requestedTier, 403);
        abort_if($actorTier === $requestedTier && $actorTier < 3, 403);
    }

    public function authorizeDelete(User $actor, User $target): void
    {
        abort_unless($actor->can('manage_users'), 403);
        $this->authorizeManageTarget($actor, $target);

        if ($target->can('manage_protected_users')) {
            abort_if($this->activeSuperAdministratorCount() <= 1, 422);
        }
    }

    public function authorizeRoleCreation(User $actor, array $permissionNames): void
    {
        abort_unless($actor->can('manage_roles'), 403);
        $this->authorizePermissionGrant($actor, $permissionNames);
    }

    public function authorizeRoleMutation(User $actor, Role $role, array $permissionNames): void
    {
        abort_unless($actor->can('manage_roles'), 403);

        if ($this->isProtectedRole($role)) {
            abort_unless($actor->can('manage_protected_users'), 403);

            if ($role->name === 'super-admin') {
                abort_if(
                    array_diff(self::SUPER_ADMIN_REQUIRED_PERMISSIONS, $permissionNames) !== [],
                    422,
                    'The super-admin role must retain its critical administration permissions.'
                );
            }

            return;
        }

        abort_if($this->userTier($actor) <= $this->roleTier($role->loadMissing('permissions')), 403);
        $this->authorizePermissionGrant($actor, $permissionNames);
    }

    public function authorizeRoleDeletion(User $actor, Role $role): void
    {
        abort_unless($actor->can('manage_roles'), 403);
        abort_if($this->isProtectedRole($role), 422);
        abort_if($this->userTier($actor) <= $this->roleTier($role->loadMissing('permissions')), 403);
    }

    public function isProtectedRole(Role $role): bool
    {
        return in_array($role->name, self::PROTECTED_ROLE_NAMES, true);
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
        $requestedPermissions = $roles
            ->flatMap(fn (Role $role): Collection => $role->permissions->pluck('name'))
            ->unique();

        abort_if($requestedPermissions->diff($actorPermissions)->isNotEmpty(), 403);
    }

    private function authorizePermissionGrant(User $actor, array $permissionNames): void
    {
        $actorTier = $this->userTier($actor);
        $requestedTier = in_array('manage_protected_users', $permissionNames, true)
            ? 3
            : (array_intersect($permissionNames, self::ADMINISTRATIVE_PERMISSIONS) !== [] ? 2 : 1);

        abort_if($actorTier < $requestedTier, 403);
        abort_if($actorTier === $requestedTier && $actorTier < 3, 403);

        if ($actorTier < 3) {
            $actorPermissions = $actor->getAllPermissions()->pluck('name');
            abort_if(collect($permissionNames)->diff($actorPermissions)->isNotEmpty(), 403);
        }
    }

    private function activeSuperAdministratorCount(): int
    {
        return User::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query
                    ->whereHas('permissions', fn ($permissionQuery) => $permissionQuery->where('name', 'manage_protected_users'))
                    ->orWhereHas('roles.permissions', fn ($permissionQuery) => $permissionQuery->where('name', 'manage_protected_users'));
            })
            ->count();
    }
}
