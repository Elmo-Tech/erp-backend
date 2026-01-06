<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds permissions for viewing sensitive project data:
     * - Budget information (cards, amounts)
     * - Project history (audit trail)
     * 
     * Only Admin/Super Admin should see these by default.
     * Employees see project details but NOT budget or history.
     */
    public function up(): void
    {
        $newPermissions = [
            'view_project_budget',   // View budget cards and financial data
            'view_project_history',  // View project audit trail/history tab
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

        // Employees do NOT get these permissions
        // They can view project details but not budget or history
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissions = ['view_project_budget', 'view_project_history'];
        
        foreach ($permissions as $permission) {
            Permission::where('name', $permission)->delete();
        }
    }
};
