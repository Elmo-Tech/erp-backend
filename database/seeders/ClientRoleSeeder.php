<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ClientRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Note: This seeder adds additional client-specific permissions
     * that are not included in AdminUserSeeder.
     */
    public function run(): void
    {
        // Additional permissions specific to client portal functionality
        $clientSpecificPermissions = [
            'view_own_projects',
            'view_own_invoices',
            'approve_ui_deliverables',
            'pay_invoices',
        ];

        foreach ($clientSpecificPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Get or create client role (may already exist from AdminUserSeeder)
        $clientRole = Role::firstOrCreate([
            'name' => 'client',
            'guard_name' => 'web',
        ]);

        // Get current permissions and add the new ones
        $currentPermissions = $clientRole->permissions->pluck('name')->toArray();
        $allClientPermissions = array_unique(array_merge($currentPermissions, $clientSpecificPermissions));
        
        // Sync all permissions to client role
        $clientRole->syncPermissions($allClientPermissions);

        $this->command->info('Client role additional permissions created successfully.');
    }
}