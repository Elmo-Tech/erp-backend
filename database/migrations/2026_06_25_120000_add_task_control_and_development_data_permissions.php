<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'view_task_control',
            'manage_wiki',
            'view_wiki',
            'create_wiki',
            'edit_wiki',
            'delete_wiki',
            'view_encrypted_content',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $this->givePermissions('founder', $permissions);
        $this->givePermissions('admin', $permissions);
        $this->givePermissions('pmo', $permissions);
        $this->givePermissions('manager', ['view_task_control', 'view_wiki', 'create_wiki', 'edit_wiki', 'view_encrypted_content']);
        $this->givePermissions('techdev', ['view_wiki', 'create_wiki', 'edit_wiki', 'view_encrypted_content']);
        $this->givePermissions('qa', ['view_wiki', 'view_encrypted_content']);
        $this->givePermissions('dev', ['view_wiki', 'view_encrypted_content']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissions = [
            'view_task_control',
            'manage_wiki',
            'view_wiki',
            'create_wiki',
            'edit_wiki',
            'delete_wiki',
            'view_encrypted_content',
        ];

        foreach (Role::all() as $role) {
            $role->revokePermissionTo($permissions);
        }

        Permission::whereIn('name', $permissions)->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function givePermissions(string $roleName, array $permissions): void
    {
        $role = Role::where('name', $roleName)->first();

        if ($role === null) {
            return;
        }

        $role->givePermissionTo($permissions);
    }
};
