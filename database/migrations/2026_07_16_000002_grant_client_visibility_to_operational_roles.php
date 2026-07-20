<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const ROLE_NAMES = ['admin', 'pmo', 'manager'];

    public function up(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'view_clients')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $rows = DB::table('roles')
            ->whereIn('name', self::ROLE_NAMES)
            ->where('guard_name', 'web')
            ->pluck('id')
            ->map(fn (int $roleId): array => [
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ])
            ->all();

        if ($rows !== []) {
            DB::table('role_has_permissions')->insertOrIgnore($rows);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'view_clients')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', self::ROLE_NAMES)
            ->where('guard_name', 'web')
            ->pluck('id');

        if ($roleIds->isEmpty()) {
            return;
        }

        DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->whereIn('role_id', $roleIds)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
