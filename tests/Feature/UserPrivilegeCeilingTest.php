<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserPrivilegeCeilingTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_cannot_assign_super_admin_role(): void
    {
        $administrator = $this->userWithPermissions([
            'manage_users',
            'assign_user_roles',
        ]);
        $target = User::factory()->create();
        $this->superAdminRole();

        $this->actingAs($administrator, 'sanctum')
            ->putJson("/api/v1/admin/users/manage/{$target->id}/roles", [
                'roles' => ['super-admin'],
            ])
            ->assertForbidden();

        $this->assertFalse($target->fresh()->hasRole('super-admin'));
    }

    public function test_super_administrator_can_assign_super_admin_role(): void
    {
        $superAdministrator = User::factory()->create();
        $superAdministrator->assignRole($this->superAdminRole());
        $target = User::factory()->create();

        $this->actingAs($superAdministrator, 'sanctum')
            ->putJson("/api/v1/admin/users/manage/{$target->id}/roles", [
                'roles' => ['super-admin'],
            ])
            ->assertOk();

        $this->assertTrue($target->fresh()->hasRole('super-admin'));
    }

    public function test_super_administrator_can_change_admin_permissions(): void
    {
        $superAdministrator = User::factory()->create();
        $superAdministrator->assignRole($this->superAdminRole());
        $adminRole = Role::findOrCreate('admin', 'web');
        $viewTasks = Permission::findOrCreate('view_tasks', 'web');
        $createTasks = Permission::findOrCreate('create_tasks', 'web');
        $adminRole->givePermissionTo($viewTasks);

        $this->actingAs($superAdministrator, 'sanctum')
            ->putJson("/api/v1/admin/roles/{$adminRole->id}", [
                'name' => 'admin',
                'permissions' => [$viewTasks->name, $createTasks->name],
            ])
            ->assertOk();

        $adminRole->refresh();
        $this->assertTrue($adminRole->hasPermissionTo('view_tasks'));
        $this->assertTrue($adminRole->hasPermissionTo('create_tasks'));
    }

    public function test_role_manager_cannot_change_admin_permissions(): void
    {
        $roleManager = $this->userWithPermissions(['manage_roles']);
        $adminRole = Role::findOrCreate('admin', 'web');
        $viewTasks = Permission::findOrCreate('view_tasks', 'web');
        $beforePermissionIds = $adminRole->permissions()->pluck('permissions.id')->sort()->values()->all();

        $this->actingAs($roleManager, 'sanctum')
            ->putJson("/api/v1/admin/roles/{$adminRole->id}", [
                'name' => 'admin',
                'permissions' => [$viewTasks->name],
            ])
            ->assertForbidden();

        $this->assertSame(
            $beforePermissionIds,
            $adminRole->fresh()->permissions()->pluck('permissions.id')->sort()->values()->all()
        );
    }

    public function test_super_admin_role_cannot_lose_critical_permissions(): void
    {
        $role = $this->superAdminRole();
        $superAdministrator = User::factory()->create();
        $superAdministrator->assignRole($role);

        $this->actingAs($superAdministrator, 'sanctum')
            ->putJson("/api/v1/admin/roles/{$role->id}", [
                'name' => 'super-admin',
                'permissions' => ['manage_roles'],
            ])
            ->assertUnprocessable();

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('manage_protected_users'));
        $this->assertTrue($role->hasPermissionTo('assign_user_roles'));
    }

    private function superAdminRole(): Role
    {
        $role = Role::findOrCreate('super-admin', 'web');

        foreach ([
            'manage_users',
            'manage_roles',
            'assign_user_roles',
            'manage_protected_users',
        ] as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $role;
    }

    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }
}
