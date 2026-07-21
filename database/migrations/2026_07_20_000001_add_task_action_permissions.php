<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'change_task_status',
        'complete_tasks',
        'reopen_tasks',
        'toggle_project_task_deliverables',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Existing task managers retain their current umbrella behavior.
        // Founder receives the exact capabilities so they are visible and
        // configurable immediately on already-provisioned installations.
        Role::where('name', 'founder')->first()?->givePermissionTo(self::PERMISSIONS);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (Role::all() as $role) {
            foreach (self::PERMISSIONS as $permission) {
                $role->revokePermissionTo($permission);
            }
        }

        Permission::whereIn('name', self::PERMISSIONS)->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
