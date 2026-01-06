<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create permissions for project access control
        $permissions = [
            'view_all_projects',    // Can view all projects regardless of team membership
            'manage_all_projects',  // Can edit all projects regardless of team membership
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web']
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissions = [
            'view_all_projects',
            'manage_all_projects',
        ];

        Permission::whereIn('name', $permissions)->delete();
    }
};
