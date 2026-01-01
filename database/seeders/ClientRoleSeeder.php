<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ClientRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions for client role
        $permissions = [
            'view_own_projects',
            'view_own_invoices',
            'approve_ui_deliverables',
            'pay_invoices',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create client role
        $clientRole = Role::firstOrCreate(['name' => 'client']);

        // Assign permissions to client role
        $clientRole->syncPermissions($permissions);

        $this->command->info('Client role and permissions created successfully.');
    }
}