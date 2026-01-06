<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class NewPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Adds new permissions for:
     * - Task creation by type (main, weekly, subtask)
     * - Time entry editing
     * - Attendance editing
     */
    public function run(): void
    {
        // New permissions to add
        $newPermissions = [
            // Task Creation Permissions
            'create_main_tasks',
            'create_weekly_tasks',
            'create_subtasks',
            
            // Task Edit Permissions
            'edit_main_tasks',
            'edit_weekly_tasks',
            'edit_subtasks',
            
            // Time Entry Permissions
            'edit_time_entries',
            
            // Attendance Permissions
            'edit_attendance',
        ];

        // Create permissions
        foreach ($newPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign permissions to roles
        $superAdmin = Role::where('name', 'super-admin')->first();
        $admin = Role::where('name', 'admin')->first();
        $developer = Role::where('name', 'developer')->first();

        // Super Admin gets all permissions
        if ($superAdmin) {
            $superAdmin->givePermissionTo($newPermissions);
        }

        // Admin gets all permissions
        if ($admin) {
            $admin->givePermissionTo($newPermissions);
        }

        // Developer can only create and edit subtasks
        if ($developer) {
            $developer->givePermissionTo([
                'create_subtasks',
                'edit_subtasks',
            ]);
        }
    }
}
