<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->boolean('is_system')->default(false)->after('guard_name');
        });

        Role::query()
            ->whereIn('name', ['founder', 'admin', 'pmo', 'techdev', 'dev', 'client'])
            ->update(['is_system' => true]);

        $permissions = collect([
            'assign_user_roles',
            'reset_user_credentials',
            'manage_protected_users',
            'protected_user_account',
        ])->map(fn (string $name): Permission => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        Role::query()->where('name', 'founder')->first()?->givePermissionTo($permissions);
        Role::query()->where('name', 'admin')->first()?->givePermissionTo('protected_user_account');
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', [
            'assign_user_roles',
            'reset_user_credentials',
            'manage_protected_users',
            'protected_user_account',
        ])->delete();

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn('is_system');
        });
    }
};
