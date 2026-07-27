<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'manage_project_workflows',
        'manage_workflow_templates',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $this->grant('founder', self::PERMISSIONS);
        $this->grant('pmo', self::PERMISSIONS);
        $this->grant('manager', ['manage_project_workflows']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (Role::all() as $role) {
            $role->revokePermissionTo(self::PERMISSIONS);
        }

        Permission::whereIn('name', self::PERMISSIONS)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function grant(string $roleName, array $permissions): void
    {
        Role::where('name', $roleName)->first()?->givePermissionTo($permissions);
    }
};
