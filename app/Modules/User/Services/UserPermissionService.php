<?php

namespace App\Modules\User\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Permission\Models\Permission;

class UserPermissionService
{
    /**
     * Get all permissions for a user with access status
     */
    public function getUserPermissions(Authenticatable $user): array
    {
        $permissions = Permission::all()->pluck('name')->toArray();

        return array_map(function ($permission) use ($user) {
            return [
                'permissionName' => $permission,
                'access' => $user->can($permission),
            ];
        }, $permissions);
    }

    /**
     * Get only the permissions that the user has access to
     */
    public function getUserAllowedPermissions(Authenticatable $user): array
    {
        return $user->getAllPermissions()->pluck('name')->toArray();
    }

    /**
     * Check if user has specific permission
     */
    public function hasPermission(Authenticatable $user, string $permission): bool
    {
        return $user->can($permission);
    }

    /**
     * Check if user has any of the given permissions
     */
    public function hasAnyPermission(Authenticatable $user, array $permissions): bool
    {
        return $user->hasAnyPermission($permissions);
    }

    /**
     * Check if user has all of the given permissions
     */
    public function hasAllPermissions(Authenticatable $user, array $permissions): bool
    {
        return $user->hasAllPermissions($permissions);
    }
}
