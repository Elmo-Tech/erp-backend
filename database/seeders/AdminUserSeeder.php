<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public const ADMIN_PERMISSIONS = [
        'manage_users',
        'view_users',
        'create_users',
        'edit_users',
        'delete_users',
        'manage_roles',
        'view_roles',
        'create_roles',
        'edit_roles',
        'delete_roles',
        'manage_clients',
        'view_clients',
        'create_clients',
        'edit_clients',
        'delete_clients',
        'manage_client_portal_access',
        'create_client_portal_access',
        'revoke_client_portal_access',
        'reset_client_portal_password',
        'manage_projects',
        'view_projects',
        'view_all_projects',
        'create_projects',
        'edit_projects',
        'delete_projects',
        'manage_project_members',
        'view_project_members',
        'add_project_members',
        'remove_project_members',
        'manage_project_files',
        'view_project_files',
        'upload_project_files',
        'delete_project_files',
        'view_project_history',
        'manage_wiki',
        'view_wiki',
        'create_wiki',
        'edit_wiki',
        'delete_wiki',
        'view_encrypted_content',
        'manage_tasks',
        'view_tasks',
        'create_tasks',
        'edit_tasks',
        'delete_tasks',
        'assign_tasks',
        'create_main_tasks',
        'create_weekly_tasks',
        'create_subtasks',
        'edit_main_tasks',
        'edit_weekly_tasks',
        'edit_subtasks',
        'manage_task_team',
        'view_internal_notes',
        'manage_time_entries',
        'view_time_entries',
        'start_timer',
        'stop_timer',
        'edit_time_entries',
        'view_all_time_entries',
        'manage_attendance',
        'view_attendance',
        'view_all_attendance',
        'edit_attendance',
        'clock_out',
        'manage_employees',
        'view_employees',
        'create_employees',
        'edit_employees',
        'view_dashboard',
        'view_dashboard_overview',
        'view_reports',
        'view_admin_stats',
        'view_attendance_analytics',
        'view_audit_logs',
        'manage_tags',
        'view_tags',
    ];

    public const MANAGER_PERMISSIONS = [
        'view_users',
        'view_clients',
        'create_clients',
        'edit_clients',
        'create_client_portal_access',
        'view_projects',
        'view_all_projects',
        'create_projects',
        'edit_projects',
        'manage_project_members',
        'view_project_members',
        'add_project_members',
        'remove_project_members',
        'manage_project_files',
        'view_project_files',
        'upload_project_files',
        'delete_project_files',
        'view_project_history',
        'view_wiki',
        'create_wiki',
        'edit_wiki',
        'manage_tasks',
        'view_tasks',
        'create_tasks',
        'edit_tasks',
        'delete_tasks',
        'assign_tasks',
        'create_main_tasks',
        'create_weekly_tasks',
        'create_subtasks',
        'edit_main_tasks',
        'edit_weekly_tasks',
        'edit_subtasks',
        'manage_task_team',
        'view_internal_notes',
        'view_time_entries',
        'start_timer',
        'stop_timer',
        'edit_time_entries',
        'view_all_time_entries',
        'view_attendance',
        'view_all_attendance',
        'edit_attendance',
        'clock_out',
        'view_dashboard',
        'view_dashboard_overview',
        'view_reports',
        'view_admin_stats',
        'view_attendance_analytics',
        'view_tags',
    ];

    public const DEVELOPER_PERMISSIONS = [
        'view_projects',
        'view_project_members',
        'view_project_files',
        'upload_project_files',
        'view_project_history',
        'view_wiki',
        'create_wiki',
        'edit_wiki',
        'view_tasks',
        'create_tasks',
        'edit_tasks',
        'create_subtasks',
        'edit_subtasks',
        'view_time_entries',
        'start_timer',
        'stop_timer',
        'view_attendance',
        'clock_out',
        'view_dashboard',
        'view_dashboard_overview',
        'view_tags',
    ];

    public const CLIENT_PERMISSIONS = [
        'view_own_projects',
        'view_dashboard',
    ];

    /**
     * Seed the admin user with super-admin role.
     */
    public function run(): void
    {
        // Create permissions
        $permissions = [
            // User management
            'manage_users',
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',

            // Role management
            'manage_roles',
            'view_roles',
            'create_roles',
            'edit_roles',
            'delete_roles',

            // Client management
            'manage_clients',
            'view_clients',
            'create_clients',
            'edit_clients',
            'delete_clients',

            // Project management
            'manage_projects',
            'view_projects',
            'create_projects',
            'edit_projects',
            'delete_projects',

            // Project members
            'manage_project_members',
            'view_project_members',
            'add_project_members',
            'remove_project_members',

            // Project files
            'manage_project_files',
            'view_project_files',
            'upload_project_files',
            'delete_project_files',

            // Project history
            'view_project_history',

            // Task management
            'manage_tasks',
            'view_tasks',
            'create_tasks',
            'edit_tasks',
            'delete_tasks',
            'view_internal_notes',
            'assign_tasks',

            // Task test scenarios
            'manage_test_scenarios',
            'view_test_scenarios',
            'create_test_scenarios',
            'complete_test_scenarios',
            'verify_test_scenarios',

            // Wiki management
            'manage_wiki',
            'view_wiki',
            'create_wiki',
            'edit_wiki',
            'delete_wiki',
            'view_encrypted_content',

            // Time tracking
            'manage_time_entries',
            'view_time_entries',
            'start_timer',
            'stop_timer',
            'view_all_time_entries',

            // Attendance
            'manage_attendance',
            'view_attendance',
            'view_all_attendance',
            'clock_out',

            // Employee management
            'manage_employees',
            'view_employees',
            'create_employees',
            'edit_employees',
            'view_employee_salary',
            'view_employee_performance',

            // Dashboard
            'view_dashboard',
            'view_dashboard_overview',

            // Reports & Analytics
            'view_reports',
            'view_profitability',
            'view_cost',
            'view_quality_metrics',
            'view_user_performance',
            'view_attendance_analytics',

            // Admin Statistics & Financial Data
            'view_admin_stats',
            'view_internal_costs',
            'manage_internal_costs',

            // Security & Audit
            'view_audit_logs',

            // Tags
            'manage_tags',
            'view_tags',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $developerRole = Role::firstOrCreate(['name' => 'developer', 'guard_name' => 'web']);
        $clientRole = Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);

        // Assign all permissions to super-admin
        $superAdminRole->syncPermissions(Permission::all());

        $adminRole->syncPermissions(self::existingPermissions(self::ADMIN_PERMISSIONS));

        // Assign permissions to manager
        $managerRole->syncPermissions(self::existingPermissions(self::MANAGER_PERMISSIONS));

        // Assign permissions to developer
        $developerRole->syncPermissions(self::existingPermissions(self::DEVELOPER_PERMISSIONS));

        $clientRole->syncPermissions(self::existingPermissions(self::CLIENT_PERMISSIONS));

        // Create super admin user
        $admin = User::firstOrCreate(
            ['email' => 'm.saber@elmotech.it'],
            [
                'name' => 'Mahmoud Saber',
                'password' => 'Mahmoud_2091.',
                'email_verified_at' => now(),
                'is_active' => true,
                'is_employee' => true,
                'work_hours_per_day' => 8,
                'work_start_time' => '10:00:00',
                'grace_days_allowed' => 5,
                'grace_days_used' => 0,
            ]
        );

        // Assign super-admin role
        if (! $admin->hasRole('super-admin')) {
            $admin->assignRole('super-admin');
        }

        $this->command->info('Admin user created successfully!');
        $this->command->info('Email: m.saber@elmotech.it');
        $this->command->info('Password: Mahmoud_2091.');
    }

    public static function existingPermissions(array $permissions)
    {
        return Permission::whereIn('name', $permissions)->get();
    }
}
