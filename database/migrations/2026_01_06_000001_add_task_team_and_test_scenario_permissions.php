<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds permissions for:
     * - Managing task team (assignees) on Main/Weekly tasks
     * - Managing test scenarios on Main/Weekly tasks
     */
    public function up(): void
    {
        // New permissions
        $newPermissions = [
            'manage_task_team',           // Add/remove team members on Main/Weekly tasks
            'manage_task_test_scenarios', // Add/edit/delete test scenarios on Main/Weekly tasks
        ];

        // Create permissions
        foreach ($newPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign to Super Admin
        $superAdmin = Role::where('name', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($newPermissions);
        }

        // Assign to Admin
        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $admin->givePermissionTo($newPermissions);
        }

        // Developer does NOT get these permissions
        // They can only manage team/test scenarios on Subtasks
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissions = ['manage_task_team', 'manage_task_test_scenarios'];
        
        foreach ($permissions as $permission) {
            Permission::where('name', $permission)->delete();
        }
    }
};
