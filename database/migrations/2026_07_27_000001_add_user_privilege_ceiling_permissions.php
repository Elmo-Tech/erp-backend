<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $assignRoles = Permission::firstOrCreate([
            'name' => 'assign_user_roles',
            'guard_name' => 'web',
        ]);
        $manageProtectedUsers = Permission::firstOrCreate([
            'name' => 'manage_protected_users',
            'guard_name' => 'web',
        ]);

        Role::query()
            ->with('permissions')
            ->get()
            ->filter(fn (Role $role): bool => $role->hasPermissionTo('manage_users'))
            ->each(fn (Role $role) => $role->givePermissionTo($assignRoles));

        $superAdmin = Role::query()->where('name', 'super-admin')->first();

        if ($superAdmin !== null) {
            $superAdmin->givePermissionTo([
                $assignRoles,
                $manageProtectedUsers,
                Permission::firstOrCreate(['name' => 'manage_users', 'guard_name' => 'web']),
                Permission::firstOrCreate(['name' => 'manage_roles', 'guard_name' => 'web']),
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->whereIn('name', ['assign_user_roles', 'manage_protected_users'])
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
