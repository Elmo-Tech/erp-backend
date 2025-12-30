<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

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
            
            // Project management
            'manage_projects',
            'view_projects',
            'create_projects',
            'edit_projects',
            'delete_projects',
            
            // Task management
            'manage_tasks',
            'view_tasks',
            'create_tasks',
            'edit_tasks',
            'delete_tasks',
            'view_internal_notes',
            
            // Sales management
            'manage_sales',
            'view_leads',
            'create_leads',
            'edit_leads',
            'delete_leads',
            'view_quotations',
            'create_quotations',
            'edit_quotations',
            'delete_quotations',
            
            // Wiki management
            'manage_wiki',
            'view_wiki',
            'create_wiki',
            'edit_wiki',
            'delete_wiki',
            'view_encrypted_content',
            
            // Attendance
            'manage_attendance',
            'view_attendance',
            'view_all_attendance',
            
            // Reports & Analytics
            'view_reports',
            'view_profitability',
            'view_cost',
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

        // Assign permissions to admin (all except cost/profitability)
        $adminPermissions = Permission::whereNotIn('name', ['view_profitability', 'view_cost'])->get();
        $adminRole->syncPermissions($adminPermissions);

        // Assign permissions to manager
        $managerPermissions = [
            'view_users',
            'manage_projects', 'view_projects', 'create_projects', 'edit_projects',
            'manage_tasks', 'view_tasks', 'create_tasks', 'edit_tasks', 'delete_tasks', 'view_internal_notes',
            'view_leads', 'create_leads', 'edit_leads',
            'view_quotations', 'create_quotations', 'edit_quotations',
            'view_wiki', 'create_wiki', 'edit_wiki',
            'view_attendance', 'view_all_attendance',
            'view_reports',
        ];
        $managerRole->syncPermissions($managerPermissions);

        // Assign permissions to developer
        $developerPermissions = [
            'view_projects',
            'view_tasks', 'create_tasks', 'edit_tasks',
            'view_wiki', 'create_wiki', 'edit_wiki',
            'view_attendance',
        ];
        $developerRole->syncPermissions($developerPermissions);

        // Client has minimal permissions (handled by policies)
        $clientPermissions = [
            'view_projects',
        ];
        $clientRole->syncPermissions($clientPermissions);

        // Create super admin user
        $admin = User::firstOrCreate(
            ['email' => 'm.saber@elmotech.it'],
            [
                'name' => 'Mahmoud Saber',
                'password' => 'Mahmoud_2091.',
                'email_verified_at' => now(),
            ]
        );

        // Assign super-admin role
        if (!$admin->hasRole('super-admin')) {
            $admin->assignRole('super-admin');
        }

        $this->command->info('Admin user created successfully!');
        $this->command->info('Email: m.saber@elmotech.it');
        $this->command->info('Password: Mahmoud_2091.');
    }
}
