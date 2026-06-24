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

        // Assign to super-admin role (lowercase with dash)
        $superAdminRole = Role::where('name', 'super-admin')->first();
        if ($superAdminRole && ! $superAdminRole->hasPermissionTo('manage_deliverables')) {
            $superAdminRole->givePermissionTo('manage_deliverables');
            $this->command->info('✅ manage_deliverables assigned to super-admin role');
        }

        $this->command->info('✅ Deliverable permissions seeding completed');
    }
}
