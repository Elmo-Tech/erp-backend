<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create manage_attendance permission if not exists
        $permission = Permission::firstOrCreate([
            'name' => 'manage_attendance',
            'guard_name' => 'web',
        ]);

        // Assign to super-admin role
        $superAdmin = Role::where('name', 'super-admin')->first();
        if ($superAdmin && ! $superAdmin->hasPermissionTo('manage_attendance')) {
            $superAdmin->givePermissionTo($permission);
        }

        // Assign to admin role
        $admin = Role::where('name', 'admin')->first();
        if ($admin && ! $admin->hasPermissionTo('manage_attendance')) {
            $admin->givePermissionTo($permission);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't remove the permission on rollback
    }
};
