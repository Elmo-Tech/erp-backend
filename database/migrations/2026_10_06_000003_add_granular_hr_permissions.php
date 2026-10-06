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

        // Client document section 15: granular HR permissions instead of a
        // single manage_employees gate. manage_employees remains as a legacy
        // fallback so existing installs keep working.
        $permissions = [
            'manage_employee_contracts',
            'manage_leaves',
            'manage_permissions',
            'manage_deductions',
            'manage_overtime',
            'manage_employee_documents',
            'manage_supervision',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Role::query()
            ->whereIn('name', ['super-admin', 'admin', 'manager'])
            ->each(function (Role $role) use ($permissions): void {
                foreach ($permissions as $name) {
                    if (! $role->hasPermissionTo($name)) {
                        $role->givePermissionTo($name);
                    }
                }
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->whereIn('name', [
                'manage_employee_contracts',
                'manage_leaves',
                'manage_permissions',
                'manage_deductions',
                'manage_overtime',
                'manage_employee_documents',
                'manage_supervision',
            ])
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
