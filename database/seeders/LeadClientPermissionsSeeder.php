<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class LeadClientPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Adds permissions for Lead Conversion and Client Portal Access management.
     */
    public function run(): void
    {
        // New permissions for Lead Conversion and Client Portal Access
        $permissions = [
            // Lead Conversion
            'convert_leads',
            'view_lead_conversion_status',
            
            // Client Portal Access Management
            'manage_client_portal_access',
            'create_client_portal_access',
            'revoke_client_portal_access',
            'reset_client_portal_password',
            
            // Points System
            'view_points',
            'manage_points',
            'award_points',
            'deduct_points',
            'view_point_transactions',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign to super-admin role
        $superAdmin = Role::where('name', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permissions);
        }

        // Assign to admin role
        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $admin->givePermissionTo([
                'convert_leads',
                'view_lead_conversion_status',
                'manage_client_portal_access',
                'create_client_portal_access',
                'revoke_client_portal_access',
                'reset_client_portal_password',
                'view_points',
                'view_point_transactions',
            ]);
        }

        // Assign to manager role
        $manager = Role::where('name', 'manager')->first();
        if ($manager) {
            $manager->givePermissionTo([
                'convert_leads',
                'view_lead_conversion_status',
                'create_client_portal_access',
                'view_points',
                'view_point_transactions',
            ]);
        }

        $this->command->info('Lead & Client Portal permissions created successfully.');
    }
}
