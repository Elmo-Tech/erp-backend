<?php

use App\Modules\Settings\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_dependencies', function (Blueprint $table): void {
            $table->foreignIdFor(User::class, 'resolved_by')
                ->nullable()
                ->after('resolved_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        $escalate = Permission::firstOrCreate([
            'name' => 'escalate_blockers',
            'guard_name' => 'web',
        ]);
        $resolve = Permission::firstOrCreate([
            'name' => 'resolve_blockers',
            'guard_name' => 'web',
        ]);

        Role::query()->whereIn('name', ['founder', 'admin', 'pmo'])
            ->each(fn (Role $role) => $role->givePermissionTo($escalate));
        Role::query()->whereIn('name', ['founder', 'admin'])
            ->each(fn (Role $role) => $role->givePermissionTo($resolve));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->whereIn('name', ['escalate_blockers', 'resolve_blockers'])
            ->where('guard_name', 'web')
            ->delete();

        Schema::table('task_dependencies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('resolved_by');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
