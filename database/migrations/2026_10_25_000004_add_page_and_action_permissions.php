<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        foreach (PermissionCatalog::definitions() as $name => $group) {
            Permission::findOrCreate($name, 'web');
        }
        foreach (Role::whereIn('name', ['admin', 'super-admin'])->get() as $role) {
            $role->givePermissionTo(array_keys(PermissionCatalog::definitions()));
        }
        foreach (Role::where('name', '!=', 'client')->get() as $role) {
            $role->givePermissionTo(['view_profile', 'view_my_requests']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Keep assigned permissions on rollback; removing them would change live roles.
    }
};
