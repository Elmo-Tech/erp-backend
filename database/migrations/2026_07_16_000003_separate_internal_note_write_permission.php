<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $writePermission = Permission::firstOrCreate([
            'name' => 'create_internal_notes',
            'guard_name' => 'web',
        ]);

        Role::query()
            ->whereHas('permissions', fn ($query) => $query->where('name', 'view_internal_notes'))
            ->each(fn (Role $role) => $role->givePermissionTo($writePermission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where('name', 'create_internal_notes')
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
