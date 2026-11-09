<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'view_all_tasks', 'guard_name' => 'web']);
        foreach (['admin', 'super-admin', 'manager'] as $name) Role::where('name', $name)->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
    public function down(): void
    {
        // Preserve existing role assignments when rolling back application code.
    }
};
