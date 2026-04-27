<?php

namespace Database\Seeders;

use App\Modules\User\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
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
            'approve_project_ui',

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

            // Sales management - Leads
            'manage_sales',
            'view_leads',
            'create_leads',
            'edit_leads',
            'delete_leads',

            // Sales management - Quotations
            'view_quotations',
            'create_quotations',
            'edit_quotations',
            'delete_quotations',
            'calculate_quotations',

            // Sales management - Contracts
            'view_contracts',
            'create_contracts',
            'edit_contracts',
            'delete_contracts',
            'sign_contracts',

            // Invoice management
            'manage_invoices',
            'view_invoices',
            'create_invoices',
            'edit_invoices',
            'delete_invoices',

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

        // Assign permissions to admin (all except cost/profitability and internal costs)
        $adminPermissions = Permission::whereNotIn('name', [
            'view_profitability',
            'view_cost',
            'view_internal_costs',
            'manage_internal_costs',
        ])->get();
        $adminRole->syncPermissions($adminPermissions);

        // Assign permissions to manager
        $managerPermissions = [
            // Users (view only)
            'view_users',

            // Clients
            'view_clients', 'create_clients', 'edit_clients',

            // Projects
            'manage_projects', 'view_projects', 'create_projects', 'edit_projects',
            'approve_project_ui',
            'manage_project_members', 'view_project_members', 'add_project_members', 'remove_project_members',
            'manage_project_files', 'view_project_files', 'upload_project_files', 'delete_project_files',
            'view_project_history',

            // Tasks
            'manage_tasks', 'view_tasks', 'create_tasks', 'edit_tasks', 'delete_tasks',
            'view_internal_notes', 'assign_tasks',
            'manage_test_scenarios', 'view_test_scenarios', 'create_test_scenarios',
            'complete_test_scenarios', 'verify_test_scenarios',

            // Sales
            'view_leads', 'create_leads', 'edit_leads',
            'view_quotations', 'create_quotations', 'edit_quotations', 'calculate_quotations',
            'view_contracts', 'create_contracts', 'edit_contracts', 'sign_contracts',

            // Invoices
            'view_invoices', 'create_invoices', 'edit_invoices',

            // Wiki
            'view_wiki', 'create_wiki', 'edit_wiki',

            // Time tracking
            'view_time_entries', 'start_timer', 'stop_timer', 'view_all_time_entries',

            // Attendance
            'view_attendance', 'view_all_attendance', 'clock_out',

            // Dashboard & Reports
            'view_dashboard', 'view_dashboard_overview',
            'view_reports', 'view_quality_metrics', 'view_user_performance', 'view_attendance_analytics',

            // Tags
            'view_tags',
        ];
        $managerRole->syncPermissions($managerPermissions);

        // Assign permissions to developer
        $developerPermissions = [
            // Projects (view only)
            'view_projects',
            'view_project_members',
            'view_project_files',
            'view_project_history',

            // Tasks
            'view_tasks', 'create_tasks', 'edit_tasks',
            'view_test_scenarios', 'complete_test_scenarios',

            // Wiki
            'view_wiki', 'create_wiki', 'edit_wiki',

            // Time tracking
            'view_time_entries', 'start_timer', 'stop_timer',

            // Attendance
            'view_attendance', 'clock_out',

            // Dashboard
            'view_dashboard', 'view_dashboard_overview',

            // Tags
            'view_tags',
        ];
        $developerRole->syncPermissions($developerPermissions);

        // Client has minimal permissions (handled by policies)
        $clientPermissions = [
            'view_projects',
            'view_invoices',
            'view_dashboard',
        ];
        $clientRole->syncPermissions($clientPermissions);

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
}
