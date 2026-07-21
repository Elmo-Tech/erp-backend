<?php

namespace Tests\Feature\Settings;

use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserPermissionParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_users_grants_read_only_access_on_both_user_surfaces(): void
    {
        $viewer = $this->userWithPermissions(['view_users']);
        $target = User::factory()->create();
        Sanctum::actingAs($viewer);

        $this->getJson('/api/v1/users')->assertOk();
        $this->getJson("/api/v1/users/{$target->id}")->assertOk();
        $this->getJson('/api/v1/admin/users/manage')->assertOk();
        $this->getJson("/api/v1/admin/users/manage/{$target->id}")->assertOk();

        $this->postJson('/api/v1/users', $this->createPayload('viewer-cannot-create@example.com'))
            ->assertForbidden();
    }

    public function test_create_users_grants_creation_without_role_assignment(): void
    {
        $creator = $this->userWithPermissions(['create_users']);
        Sanctum::actingAs($creator);

        $this->postJson('/api/v1/users', $this->createPayload('canonical-create@example.com'))
            ->assertCreated();
        $this->postJson('/api/v1/admin/users/manage', $this->createPayload('admin-create@example.com'))
            ->assertCreated();

        $this->getJson('/api/v1/users')->assertForbidden();
        $this->assertDatabaseHas('users', ['email' => 'canonical-create@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'admin-create@example.com']);
    }

    public function test_edit_users_updates_ordinary_users_but_not_protected_peers(): void
    {
        $editor = $this->userWithPermissions(['edit_users']);
        $canonicalTarget = User::factory()->create();
        $adminTarget = User::factory()->create();
        $protectedTarget = $this->userWithPermissions(['protected_user_account']);
        Sanctum::actingAs($editor);

        $this->putJson("/api/v1/users/{$canonicalTarget->id}", ['name' => 'Canonical Edited'])
            ->assertOk();
        $this->putJson("/api/v1/admin/users/manage/{$adminTarget->id}", ['name' => 'Admin Edited'])
            ->assertOk();
        $this->putJson("/api/v1/users/{$protectedTarget->id}", ['name' => 'Protected Edited'])
            ->assertForbidden();

        $this->assertSame('Canonical Edited', $canonicalTarget->fresh()->name);
        $this->assertSame('Admin Edited', $adminTarget->fresh()->name);
        $this->assertNotSame('Protected Edited', $protectedTarget->fresh()->name);
    }

    public function test_edit_users_cannot_bypass_dedicated_sensitive_actions(): void
    {
        $editor = $this->userWithPermissions(['edit_users']);
        $target = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($editor);

        $this->putJson("/api/v1/users/{$target->id}", $this->passwordPayload())
            ->assertForbidden();
        $this->putJson("/api/v1/users/{$target->id}", ['isActive' => false])
            ->assertForbidden();
        $this->postJson("/api/v1/admin/users/manage/{$target->id}/toggle-status")
            ->assertForbidden();

        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_password_only_update_uses_reset_permission_and_target_ceiling(): void
    {
        $resetter = $this->userWithPermissions(['reset_user_credentials']);
        $ordinaryTarget = User::factory()->create();
        $ordinaryTarget->createToken('old-session');
        $protectedTarget = $this->userWithPermissions(['protected_user_account']);
        Sanctum::actingAs($resetter);

        $this->putJson("/api/v1/users/{$ordinaryTarget->id}", $this->passwordPayload())
            ->assertOk();
        $this->putJson("/api/v1/users/{$protectedTarget->id}", $this->passwordPayload())
            ->assertForbidden();

        $this->assertSame(0, $ordinaryTarget->tokens()->count());
    }

    public function test_mixed_user_update_requires_every_matching_capability(): void
    {
        $resetter = $this->userWithPermissions(['reset_user_credentials']);
        $target = User::factory()->create();
        Sanctum::actingAs($resetter);

        $this->putJson("/api/v1/users/{$target->id}", array_merge(
            ['name' => 'Mixed Update'],
            $this->passwordPayload()
        ))->assertForbidden();

        $resetter->givePermissionTo(Permission::findOrCreate('edit_users', 'web'));
        $this->putJson("/api/v1/users/{$target->id}", array_merge(
            ['name' => 'Mixed Update'],
            $this->passwordPayload()
        ))->assertOk();

        $this->assertSame('Mixed Update', $target->fresh()->name);
    }

    public function test_role_and_status_only_updates_use_their_exact_permissions(): void
    {
        $roleOperator = $this->userWithPermissions(['assign_user_roles']);
        $target = User::factory()->create(['is_active' => true]);
        $role = Role::findOrCreate('empty-operational-role', 'web');
        Sanctum::actingAs($roleOperator);

        $this->putJson("/api/v1/users/{$target->id}", ['roles' => [$role->name]])
            ->assertOk();
        $this->assertTrue($target->fresh()->hasRole($role));

        $statusOperator = $this->userWithPermissions(['toggle_user_status']);
        Sanctum::actingAs($statusOperator);
        $this->putJson("/api/v1/users/{$target->id}", ['isActive' => false])
            ->assertOk();
        $this->assertFalse($target->fresh()->is_active);
    }

    public function test_toggle_user_status_is_granular_and_respects_the_target_ceiling(): void
    {
        $operator = $this->userWithPermissions(['toggle_user_status']);
        $ordinaryTarget = User::factory()->create(['is_active' => true]);
        $protectedTarget = $this->userWithPermissions(['protected_user_account']);
        Sanctum::actingAs($operator);

        $this->postJson("/api/v1/admin/users/manage/{$ordinaryTarget->id}/toggle-status")
            ->assertOk();
        $this->postJson("/api/v1/admin/users/manage/{$protectedTarget->id}/toggle-status")
            ->assertForbidden();

        $this->assertFalse($ordinaryTarget->fresh()->is_active);
        $this->assertTrue($protectedTarget->fresh()->is_active);
    }

    public function test_manage_users_remains_the_ordinary_crud_umbrella_only(): void
    {
        $manager = $this->userWithPermissions(['manage_users']);
        $target = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/users')->assertOk();
        $this->postJson('/api/v1/users', $this->createPayload('umbrella-create@example.com'))
            ->assertCreated();
        $this->putJson("/api/v1/users/{$target->id}", ['name' => 'Umbrella Edited'])
            ->assertOk();
        $this->postJson("/api/v1/admin/users/manage/{$target->id}/toggle-status")
            ->assertForbidden();

        $this->assertSame('Umbrella Edited', $target->fresh()->name);
        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_permission_migration_preserves_legacy_toggle_access_for_manage_users_roles(): void
    {
        $role = Role::findOrCreate('legacy-user-manager', 'web');
        $role->givePermissionTo(Permission::findOrCreate('manage_users', 'web'));
        $role->revokePermissionTo(Permission::findOrCreate('toggle_user_status', 'web'));

        $migration = require database_path(
            'migrations/2026_07_20_000010_add_user_employee_action_permissions.php'
        );
        $migration->up();

        $this->assertTrue($role->fresh()->hasPermissionTo('toggle_user_status'));
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function createPayload(string $email): array
    {
        return [
            'name' => 'Permission Test User',
            'email' => $email,
            'password' => 'CreatePassword4$',
            'password_confirmation' => 'CreatePassword4$',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function passwordPayload(): array
    {
        return [
            'password' => 'ChangedPassword4$',
            'password_confirmation' => 'ChangedPassword4$',
        ];
    }
}
