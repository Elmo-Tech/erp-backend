<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'create_project_task_deliverables';

    public function up(): void
    {
        $permission = Permission::findOrCreate(self::PERMISSION, 'web');

        Role::where('name', 'dev')
            ->where('guard_name', 'web')
            ->first()
            ?->givePermissionTo($permission);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $developer = Role::where('name', 'dev')
            ->where('guard_name', 'web')
            ->first();
        $permission = Permission::where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->first();

        if ($developer && $permission) {
            $developer->revokePermissionTo($permission);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
