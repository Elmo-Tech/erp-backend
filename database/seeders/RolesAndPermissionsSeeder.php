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
            'manage_invoices',
            'log_time',
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
            ],
            'pmo' => [
                'view_project_timeline',
                'view_all_blockers',
                'view_morning_sop',
                'submit_morning_sop',
            ],
            'tech_lead' => [
                'view_team_performance',
                'view_project_timeline',
            ],
            'developer' => [
                'view_personal_tasks',
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
