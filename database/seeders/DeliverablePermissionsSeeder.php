<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DeliverablePermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create manage_deliverables permission if it doesn't exist
        $permission = Permission::firstOrCreate(
            ['name' => 'manage_deliverables'],
            ['guard_name' => 'web']
        );

        // Assign to Admin role
        $adminRole = Role::where('name', 'Admin')->first();
        if ($adminRole && !$adminRole->hasPermissionTo('manage_deliverables')) {
            $adminRole->givePermissionTo('manage_deliverables');
        }

        // Assign to super-admin role
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        if ($superAdminRole && !$superAdminRole->hasPermissionTo('manage_deliverables')) {
            $superAdminRole->givePermissionTo('manage_deliverables');
        }

        $this->command->info('✅ Deliverable permissions created and assigned to Admin role');
    }
}
