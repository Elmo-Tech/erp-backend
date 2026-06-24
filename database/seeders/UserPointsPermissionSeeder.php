<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserPointsPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create the permission
        $permission = Permission::firstOrCreate([
            'name' => 'manage_user_points',
            'guard_name' => 'web',
        ]);

        // Assign to super-admin only in the simplified ERP defaults.
        $superAdminRole = Role::where('name', 'super-admin')->first();
        if ($superAdminRole) {
            $superAdminRole->givePermissionTo($permission);
        }

        $this->command->info('User Points permission created for super-admin only.');
    }
}
