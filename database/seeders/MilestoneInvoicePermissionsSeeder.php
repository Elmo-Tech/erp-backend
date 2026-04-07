<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MilestoneInvoicePermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Adds permissions for Milestone-Invoice integration.
     */
    public function run(): void
    {
        // Milestone-Invoice Integration Permissions
        $permissions = [
            // Milestone management
            'view_milestones',
            'create_milestone_invoice',
            'mark_milestone_paid',
            'link_milestone_invoice',

            // Invoice from milestone
            'send_invoices',
            'cancel_invoices',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign to super-admin
        $superAdmin = Role::where('name', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permissions);
        }

        // Assign to admin
        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $admin->givePermissionTo($permissions);
        }

        // Assign to manager (limited)
        $manager = Role::where('name', 'manager')->first();
        if ($manager) {
            $manager->givePermissionTo([
                'view_milestones',
                'create_milestone_invoice',
                'mark_milestone_paid',
                'link_milestone_invoice',
                'send_invoices',
            ]);
        }

        $this->command->info('Milestone-Invoice permissions created successfully.');
    }
}
