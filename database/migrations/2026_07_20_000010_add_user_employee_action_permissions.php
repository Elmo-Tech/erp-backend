<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'toggle_user_status',
        'delete_employees',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        Role::query()
            ->whereHas('permissions', fn ($query) => $query->where('name', 'manage_users'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo('toggle_user_status'));
        Role::query()->where('name', 'founder')->first()?->givePermissionTo(self::PERMISSIONS);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (Role::all() as $role) {
            foreach (self::PERMISSIONS as $permission) {
                $role->revokePermissionTo($permission);
            }
        }

        Permission::whereIn('name', self::PERMISSIONS)->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
