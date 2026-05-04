<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed the matrix model roles and their exact dashboard permissions.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view_financial_stats',
            'view_project_timeline',
            'view_team_performance',
            'view_personal_tasks',
            'view_all_blockers',
            'view_escalated_blockers',
            'view_team_pulse',
            'manage_sops',
            'view_morning_sop',
            'submit_morning_sop',
            'manage_users',
            'manage_projects',
            'create_projects',
            'manage_invoices',
            'schedule_meetings',
            'log_time',
            // --- CRM & Settings Permissions ---
            'view_clients',
            'manage_clients',
            'view_settings',
            'manage_settings',
            // --- Lead / Sales Pipeline Permissions ---
            'view_leads',
            'manage_leads',
            // --- Project Execution Layer Permissions ---
            'view_projects',          // canonical "can see the Projects page"
            'view_all_projects',      // see every project regardless of membership
            'view_assigned_projects', // see only projects the user is a member of
            'manage_project_settings',
            'manage_tasks',
            'work_on_tasks',
            'edit_time_entries',
            'manage_change_requests',
            // --- Roles & Permissions ---
            'manage_roles',
            'view_roles',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $roles = [
            'founder' => [
                'view_financial_stats',
                'view_escalated_blockers',
                'view_team_pulse',
                'manage_sops',
                'view_clients',
                'manage_clients',
                'view_projects',
                'view_all_projects',
                'manage_projects',
                'create_projects',
                'manage_tasks',
                'manage_change_requests',
                'schedule_meetings',
                'view_settings',
                'manage_settings',
                'manage_roles',
                'view_roles',
                // Lead / Sales Pipeline
                'view_leads',
                'manage_leads',
            ],
            'pmo' => [
                'view_project_timeline',
                'view_all_blockers',
                'view_morning_sop',
                'submit_morning_sop',
                'view_projects',
                'view_all_projects',
                'create_projects',
                'schedule_meetings',
                // PMO can view the pipeline but not mutate stages
                'view_leads',
            ],
            'tech_lead' => [
                'view_team_performance',
                'view_project_timeline',
                'view_projects',
                'view_assigned_projects',
                'manage_tasks',
                'work_on_tasks',
            ],
            'developer' => [
                'view_personal_tasks',
                'view_projects',
                'view_assigned_projects',
                'work_on_tasks',
                'log_time',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ])->syncPermissions($rolePermissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
