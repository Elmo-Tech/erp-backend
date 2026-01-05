<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PerformancePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Performance Evaluation Permissions
        $permissions = [
            // Evaluation Periods
            'view_performance_evaluations',
            'manage_performance_evaluations',
            'approve_performance_evaluations',
            'view_bonuses',
            
            // Code Reviews
            'view_all_code_reviews',
            'manage_code_reviews',
            
            // Innovation Logs
            'view_all_innovation_logs',
            'verify_innovation_logs',
            'manage_innovation_logs',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign to super-admin role if exists
        $superAdmin = Role::where('name', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permissions);
        }

        // Assign to admin role if exists
        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $admin->givePermissionTo([
                'view_performance_evaluations',
                'manage_performance_evaluations',
                'view_all_code_reviews',
                'manage_code_reviews',
                'view_all_innovation_logs',
                'verify_innovation_logs',
            ]);
        }

        // Assign to manager role if exists
        $manager = Role::where('name', 'manager')->first();
        if ($manager) {
            $manager->givePermissionTo([
                'view_performance_evaluations',
                'view_all_code_reviews',
                'view_all_innovation_logs',
                'verify_innovation_logs',
            ]);
        }
    }
}
