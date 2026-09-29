<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::query()
            ->where('name', 'manage_employees')
            ->where('guard_name', 'web')
            ->first();
        $manager = Role::query()
            ->where('name', 'manager')
            ->where('guard_name', 'web')
            ->first();

        if ($permission === null || $manager === null || $manager->hasPermissionTo($permission)) {
            return;
        }

        $manager->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Preserve the permission assignment when rolling back this forward-only repair.
    }
};
