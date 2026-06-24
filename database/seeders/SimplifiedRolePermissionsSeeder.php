<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SimplifiedRolePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AdminUserSeeder::CLIENT_PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin = Role::where('name', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->syncPermissions(Permission::all());
        }

        $this->syncRole('admin', AdminUserSeeder::ADMIN_PERMISSIONS);
        $this->syncRole('manager', AdminUserSeeder::MANAGER_PERMISSIONS);
        $this->syncRole('developer', AdminUserSeeder::DEVELOPER_PERMISSIONS);
        $this->syncRole('client', AdminUserSeeder::CLIENT_PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info('Simplified ERP role permissions synchronized.');
    }

    private function syncRole(string $roleName, array $permissions): void
    {
        $role = Role::where('name', $roleName)->first();

        if (! $role) {
            return;
        }

        $role->syncPermissions(Permission::whereIn('name', $permissions)->get());
    }
}
