<?php

namespace Tests\Feature\Settings;

use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserPrivilegeCeilingTest extends TestCase
{
    use RefreshDatabase;

    public function test_manage_users_cannot_assign_roles_without_role_assignment_permission(): void
    {
        $actor = $this->userWithPermissions(['manage_users', 'view_tasks']);
        $target = User::factory()->create();
        Role::findOrCreate('developer', 'web')->givePermissionTo(
            Permission::findOrCreate('view_tasks', 'web')
        );

        Sanctum::actingAs($actor);

        $this->putJson("/api/v1/admin/users/manage/{$target->id}/roles", [
            'roles' => ['developer'],
        ])->assertForbidden();

        $this->assertFalse($target->fresh()->hasRole('developer'));
    }

    public function test_generic_create_and_update_paths_share_the_role_assignment_boundary(): void
    {
        $actor = $this->userWithPermissions(['manage_users', 'view_tasks']);
        $target = User::factory()->create();
        Role::findOrCreate('developer', 'web')->givePermissionTo(
            Permission::findOrCreate('view_tasks', 'web')
        );

        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/admin/users/manage', [
            'name' => 'Unauthorized Role User',
            'email' => 'unauthorized-role@example.com',
            'password' => 'CreatePassword4$',
            'password_confirmation' => 'CreatePassword4$',
            'roles' => ['developer'],
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'unauthorized-role@example.com']);

        $this->putJson("/api/v1/admin/users/manage/{$target->id}", [
            'roles' => ['developer'],
        ])->assertForbidden();

        $this->assertFalse($target->fresh()->hasRole('developer'));
    }

    public function test_role_assignment_enforces_protected_ceiling_and_preserves_subordinate_assignment(): void
    {
        $actor = $this->userWithPermissions(['manage_users', 'assign_user_roles', 'view_tasks']);
        $target = User::factory()->create();

        Role::findOrCreate('founder', 'web')->givePermissionTo(
            Permission::findOrCreate('manage_protected_users', 'web')
        );
        Role::findOrCreate('developer', 'web')->givePermissionTo(
            Permission::findOrCreate('view_tasks', 'web')
        );

        Sanctum::actingAs($actor);

        $this->putJson("/api/v1/admin/users/manage/{$target->id}/roles", [
            'roles' => ['founder'],
        ])->assertForbidden();

        $this->assertFalse($target->fresh()->hasRole('founder'));

        $this->putJson("/api/v1/admin/users/manage/{$target->id}/roles", [
            'roles' => ['developer'],
        ])->assertOk();

        $this->assertTrue($target->fresh()->hasRole('developer'));
    }

    public function test_password_reset_requires_dedicated_permission_and_lower_target(): void
    {
        $manager = $this->userWithPermissions(['manage_users']);
        $peerManager = $this->userWithPermissions(['manage_users']);
        $ordinaryUser = User::factory()->create();

        Sanctum::actingAs($manager);

        $this->putJson("/api/v1/admin/users/manage/{$ordinaryUser->id}", $this->passwordPayload())
            ->assertForbidden();

        $manager->givePermissionTo(Permission::findOrCreate('reset_user_credentials', 'web'));

        $this->putJson("/api/v1/admin/users/manage/{$peerManager->id}", $this->passwordPayload())
            ->assertForbidden();

        $ordinaryUser->createToken('old-session');

        $this->putJson("/api/v1/admin/users/manage/{$ordinaryUser->id}", $this->passwordPayload())
            ->assertOk();

        $this->assertSame(0, $ordinaryUser->tokens()->count());
    }

    public function test_delete_requires_dedicated_permission_on_both_user_routes(): void
    {
        $actor = $this->userWithPermissions(['manage_users']);
        $firstTarget = User::factory()->create();
        $secondTarget = User::factory()->create();

        Sanctum::actingAs($actor);

        $this->deleteJson("/api/v1/admin/users/manage/{$firstTarget->id}")->assertForbidden();
        $this->deleteJson("/api/v1/users/{$secondTarget->id}")->assertForbidden();

        $this->assertNotSoftDeleted($firstTarget);
        $this->assertNotSoftDeleted($secondTarget);
    }

    public function test_authorized_deleter_cannot_delete_peer_but_can_delete_ordinary_user(): void
    {
        $actor = $this->userWithPermissions(['manage_users', 'delete_users']);
        $peer = $this->userWithPermissions(['manage_users']);
        $ordinaryUser = User::factory()->create();
        $ordinaryUser->createToken('old-session');
        $ordinaryUser->givePermissionTo(Permission::findOrCreate('view_tasks', 'web'));

        Sanctum::actingAs($actor);

        $this->deleteJson("/api/v1/admin/users/manage/{$peer->id}")->assertForbidden();
        $this->assertNotSoftDeleted($peer);

        $this->deleteJson("/api/v1/admin/users/manage/{$ordinaryUser->id}")->assertOk();

        $this->assertSoftDeleted($ordinaryUser);
        $this->assertSame(0, $ordinaryUser->tokens()->count());
        $this->assertSame(0, $ordinaryUser->permissions()->count());
    }

    public function test_manage_roles_alone_cannot_mutate_a_protected_role(): void
    {
        $actor = $this->userWithPermissions(['manage_roles']);
        $protectedRole = Role::findOrCreate('dev', 'web');
        $protectedRole->givePermissionTo(Permission::findOrCreate('view_tasks', 'web'));
        Permission::findOrCreate('create_tasks', 'web');

        Sanctum::actingAs($actor);

        $this->putJson("/api/v1/admin/roles/{$protectedRole->id}", [
            'name' => 'dev',
            'permissions' => ['view_tasks', 'create_tasks'],
        ])->assertForbidden();

        $protectedRole->refresh();
        $this->assertSame('dev', $protectedRole->name);
        $this->assertTrue($protectedRole->hasPermissionTo('view_tasks'));
        $this->assertFalse($protectedRole->hasPermissionTo('create_tasks'));
    }

    public function test_founder_capabilities_can_sync_permissions_for_a_protected_dev_role(): void
    {
        $actor = $this->userWithPermissions([
            'edit_roles',
            'manage_protected_users',
            'view_tasks',
            'create_tasks',
        ]);
        $devRole = Role::findOrCreate('dev', 'web');
        $devRole->givePermissionTo(Permission::findOrCreate('view_tasks', 'web'));

        Sanctum::actingAs($actor);

        $this->putJson("/api/v1/admin/roles/{$devRole->id}", [
            'name' => 'dev',
            'permissions' => ['view_tasks', 'create_tasks'],
        ])->assertOk();

        $devRole->refresh();
        $this->assertSame('dev', $devRole->name);
        $this->assertTrue($devRole->hasPermissionTo('view_tasks'));
        $this->assertTrue($devRole->hasPermissionTo('create_tasks'));
    }

    public function test_protected_role_name_cannot_be_changed_by_founder_capabilities(): void
    {
        $actor = $this->userWithPermissions([
            'manage_roles',
            'manage_protected_users',
            'view_tasks',
        ]);
        $protectedRole = Role::findOrCreate('admin', 'web');
        $protectedRole->givePermissionTo(Permission::findOrCreate('view_tasks', 'web'));

        Sanctum::actingAs($actor);

        $this->putJson("/api/v1/admin/roles/{$protectedRole->id}", [
            'name' => 'renamed-admin',
            'permissions' => ['view_tasks'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $protectedRole->refresh();
        $this->assertSame('admin', $protectedRole->name);
        $this->assertTrue($protectedRole->hasPermissionTo('view_tasks'));
    }

    public function test_protected_role_sync_preserves_the_privilege_ceiling(): void
    {
        $actor = $this->userWithPermissions(['manage_roles', 'manage_protected_users']);
        $devRole = Role::findOrCreate('dev', 'web');

        Sanctum::actingAs($actor);

        $this->putJson("/api/v1/admin/roles/{$devRole->id}", [
            'permissions' => ['manage_protected_users'],
        ])->assertForbidden();

        $this->assertFalse($devRole->fresh()->hasPermissionTo('manage_protected_users'));
    }

    public function test_founder_role_cannot_lose_critical_administration_permissions(): void
    {
        $founderRole = Role::findOrCreate('founder', 'web');
        $founderRole->givePermissionTo([
            Permission::findOrCreate('manage_roles', 'web'),
            Permission::findOrCreate('manage_protected_users', 'web'),
        ]);
        $founder = User::factory()->create();
        $founder->assignRole($founderRole);

        Sanctum::actingAs($founder);

        $this->putJson("/api/v1/admin/roles/{$founderRole->id}", [
            'permissions' => ['manage_roles'],
        ])->assertForbidden();

        $founderRole->refresh();
        $this->assertTrue($founderRole->hasPermissionTo('manage_roles'));
        $this->assertTrue($founderRole->hasPermissionTo('manage_protected_users'));
    }

    public function test_existing_session_profile_reflects_protected_role_permission_sync(): void
    {
        $founder = $this->userWithPermissions([
            'manage_roles',
            'manage_protected_users',
            'view_tasks',
            'create_tasks',
        ]);
        $developer = User::factory()->create();
        $devRole = Role::findOrCreate('dev', 'web');
        $devRole->givePermissionTo(Permission::findOrCreate('view_tasks', 'web'));
        $developer->assignRole($devRole);

        $founderToken = $founder->createToken('founder-session')->plainTextToken;
        $developerToken = $developer->createToken('developer-session')->plainTextToken;

        $this->withToken($founderToken)
            ->putJson("/api/v1/admin/roles/{$devRole->id}", [
                'permissions' => ['view_tasks', 'create_tasks'],
            ])->assertOk();

        auth()->forgetGuards();

        $this->withToken($developerToken)
            ->getJson('/api/v1/auth/profile')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonFragment([
                'permissionName' => 'create_tasks',
                'access' => true,
            ]);
    }

    public function test_role_manager_cannot_create_a_role_above_their_permission_ceiling(): void
    {
        $actor = $this->userWithPermissions(['manage_roles']);
        Permission::findOrCreate('manage_protected_users', 'web');

        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/admin/roles', [
            'name' => 'escalated-role',
            'permissions' => ['manage_protected_users'],
        ])->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'escalated-role']);
    }

    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    private function passwordPayload(): array
    {
        return [
            'password' => 'ResetPassword4$',
            'password_confirmation' => 'ResetPassword4$',
        ];
    }
}
