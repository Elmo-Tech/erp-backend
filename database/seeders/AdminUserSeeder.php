<?php

namespace Database\Seeders;

use App\Enums\ClientStatus;
use App\Enums\UserSpecialization;
use App\Models\Client;
use App\Models\User;
use App\Support\PermissionCatalog;
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
        'assign_user_roles',
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
        'manage_project_workflows',
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
        'view_all_tasks',
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
        'manage_employee_contracts',
        'manage_leaves',
        'manage_permissions',
        'manage_deductions',
        'manage_overtime',
        'manage_employee_documents',
        'manage_supervision',
        'manage_hr_settings',
        'manage_leave_types',
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
        'manage_devices',
        'view_devices',
    ];

    public const MANAGER_PERMISSIONS = [
        'view_users',
        'view_clients',
        'create_clients',
        'edit_clients',
        'create_client_portal_access',
        'view_projects',
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
        'view_all_tasks',
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
        'manage_employees',
        'manage_employee_contracts',
        'manage_leaves',
        'manage_permissions',
        'manage_deductions',
        'manage_overtime',
        'manage_employee_documents',
        'manage_supervision',
        'manage_hr_settings',
        'view_dashboard',
        'view_dashboard_overview',
        'view_reports',
        'view_admin_stats',
        'view_attendance_analytics',
        'manage_leave_types',
        'view_tags',
        'view_devices',
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
     * Seed simplified ERP permissions, roles, and the default admin user.
     */
    public function run(): void
    {
        $permissions = array_values(array_unique([
            ...self::ADMIN_PERMISSIONS,
            ...self::MANAGER_PERMISSIONS,
            ...self::DEVELOPER_PERMISSIONS,
            ...self::CLIENT_PERMISSIONS,
        ]));

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        foreach (PermissionCatalog::definitions() as $permission => $group) {
            Permission::findOrCreate($permission, 'web');
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
        $adminRole->givePermissionTo(array_keys(PermissionCatalog::definitions()));

        // Assign permissions to manager
        $managerRole->syncPermissions(self::existingPermissions(self::MANAGER_PERMISSIONS));

        // Assign permissions to developer
        $developerRole->syncPermissions(self::existingPermissions(self::DEVELOPER_PERMISSIONS));
        $developerRole->givePermissionTo(['view_profile', 'view_my_requests']);
        $managerRole->givePermissionTo(['view_profile', 'view_my_requests']);

        $clientRole->syncPermissions(self::existingPermissions(self::CLIENT_PERMISSIONS));

        // Create the default The Tables super-admin user. Password can be overridden per environment.
        $admin = User::firstOrNew(['email' => 'admin@thetables.com']);
        $isNewAdmin = ! $admin->exists;

        $admin->forceFill([
            'name' => 'The Tables (Super Admin)',
            'email_verified_at' => $admin->email_verified_at ?? now(),
            'is_active' => true,
            'is_employee' => true,
            'work_hours_per_day' => $admin->work_hours_per_day ?? 8,
            'work_start_time' => $admin->work_start_time ?? '10:00:00',
            'grace_days_allowed' => $admin->grace_days_allowed ?? 5,
            'grace_days_used' => $admin->grace_days_used ?? 0,
        ]);

        if ($isNewAdmin || env('TABLES_ADMIN_PASSWORD')) {
            $admin->password = env('TABLES_ADMIN_PASSWORD', 'password');
        }

        $admin->save();

        $admin->syncRoles(['super-admin']);

        $this->seedQaAccount('QA Manager', 'manager@thetables.com', 'manager', [
            'specialization' => UserSpecialization::FULLSTACK->value,
        ]);

        $this->seedQaAccount('QA Employee', 'employee@thetables.com', 'developer', [
            'specialization' => UserSpecialization::QA->value,
        ]);

        $clientUser = $this->seedQaAccount('QA Client', 'client@thetables.com', 'client', [
            'is_employee' => false,
            'specialization' => UserSpecialization::FULLSTACK->value,
        ]);

        Client::updateOrCreate(
            ['email' => $clientUser->email],
            [
                'name' => $clientUser->name,
                'company' => 'The Tables QA Client',
                'status' => ClientStatus::ACTIVE->value,
                'user_id' => $clientUser->id,
            ]
        );

        $this->command->info('Admin user created successfully!');
        $this->command->info('Email: admin@thetables.com');
        $this->command->info('Password: set TABLES_ADMIN_PASSWORD per environment; defaults to local development password.');
    }

    private function seedQaAccount(string $name, string $email, string $role, array $extra = []): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $isNew = ! $user->exists;

        $user->forceFill(array_merge([
            'name' => $name,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'is_active' => true,
            'is_employee' => true,
            'work_hours_per_day' => $user->work_hours_per_day ?? 8,
            'work_start_time' => $user->work_start_time ?? '10:00:00',
            'grace_days_allowed' => $user->grace_days_allowed ?? 5,
            'grace_days_used' => $user->grace_days_used ?? 0,
        ], $extra));

        $envKey = 'TABLES_'.strtoupper(str_replace('-', '_', $role)).'_PASSWORD';
        if ($isNew || env($envKey)) {
            $user->password = env($envKey, 'password');
        }

        $user->save();
        $user->syncRoles([$role]);

        return $user;
    }

    public static function existingPermissions(array $permissions)
    {
        return Permission::whereIn('name', $permissions)->get();
    }
}
