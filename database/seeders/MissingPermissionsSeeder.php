<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeder for missing permissions that were added in frontend but not in backend.
 */
class MissingPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Time Entry permissions
            'edit_time_entries',
            
            // Attendance permissions
            'edit_attendance',
            
            // Project permissions
            'view_all_projects',
            'view_project_budget',
            
            // Invoice permissions
            'send_invoices',
            'cancel_invoices',
            
            // Task creation permissions (granular)
            'create_main_tasks',
            'create_weekly_tasks',
            'create_subtasks',
            
            // Task edit permissions (granular)
            'edit_main_tasks',
            'edit_weekly_tasks',
            'edit_subtasks',
            
            // Task team & test scenarios
            'manage_task_team',
            'manage_task_test_scenarios',
            
            // Change Request permissions
            'view_change_requests',
            'create_change_requests',
            'approve_change_requests',
            'reject_change_requests',
            'manage_change_requests',
            
            // Milestones permissions
            'view_milestones',
            'create_milestones',
            'edit_milestones',
            'delete_milestones',
            'manage_milestones',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $this->command->info('Missing permissions created successfully!');

        // Assign new permissions to super-admin
        $superAdminRole = Role::where('name', 'super-admin')->first();
        if ($superAdminRole) {
            $superAdminRole->syncPermissions(Permission::all());
            $this->command->info('Super-admin role updated with all permissions.');
        }

        // Assign relevant permissions to admin
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminPermissions = Permission::whereNotIn('name', [
                'view_profitability', 
                'view_cost',
                'view_internal_costs',
                'manage_internal_costs',
            ])->get();
            $adminRole->syncPermissions($adminPermissions);
            $this->command->info('Admin role updated with new permissions.');
        }

        // Assign task-related permissions to manager
        $managerRole = Role::where('name', 'manager')->first();
        if ($managerRole) {
            $managerNewPermissions = [
                'edit_time_entries',
                'view_all_projects',
                'view_project_budget',
                'send_invoices',
                'create_main_tasks',
                'create_weekly_tasks',
                'create_subtasks',
                'edit_main_tasks',
                'edit_weekly_tasks',
                'edit_subtasks',
                'manage_task_team',
                'manage_task_test_scenarios',
                'view_change_requests',
                'create_change_requests',
                'approve_change_requests',
                'reject_change_requests',
                'manage_change_requests',
                'view_milestones',
                'create_milestones',
                'edit_milestones',
                'manage_milestones',
            ];
            
            foreach ($managerNewPermissions as $permission) {
                $managerRole->givePermissionTo($permission);
            }
            $this->command->info('Manager role updated with new permissions.');
        }

        // Assign basic task permissions to developer
        $developerRole = Role::where('name', 'developer')->first();
        if ($developerRole) {
            $developerNewPermissions = [
                'create_subtasks',
                'edit_subtasks',
                'view_change_requests',
                'create_change_requests',
                'view_milestones',
            ];
            
            foreach ($developerNewPermissions as $permission) {
                $developerRole->givePermissionTo($permission);
            }
            $this->command->info('Developer role updated with new permissions.');
        }
    }
}
