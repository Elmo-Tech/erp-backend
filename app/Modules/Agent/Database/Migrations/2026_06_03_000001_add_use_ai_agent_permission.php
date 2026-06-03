<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'use_ai_agent', 'guard_name' => 'web']);

        Role::whereIn('name', ['founder', 'admin', 'pmo', 'manager', 'techdev', 'qa', 'dev'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo('use_ai_agent'));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::whereIn('name', ['founder', 'admin', 'pmo', 'manager', 'techdev', 'qa', 'dev'])
            ->get()
            ->each(fn (Role $role) => $role->revokePermissionTo('use_ai_agent'));

        Permission::where('name', 'use_ai_agent')->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
