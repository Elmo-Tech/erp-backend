<?php

namespace Tests\Feature;

use App\Modules\Settings\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminRoleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions if they don't exist
        Permission::firstOrCreate(['name' => 'manage_roles']);
        Permission::firstOrCreate(['name' => 'view_admin_stats']);

        // Create admin user with manage_roles permission
        $this->adminUser = User::factory()->create();
        $this->adminUser->givePermissionTo('manage_roles');

        // Create regular user without permissions
        $this->regularUser = User::factory()->create();

        // Create some test roles and permissions
        Role::firstOrCreate(['name' => 'test-role']);
        Role::firstOrCreate(['name' => 'admin']); // Protected role
        Permission::firstOrCreate(['name' => 'test_permission']);
    }

    public function test_index_returns_roles_for_authorized_user(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->getJson('/api/v1/admin/roles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'guardName',
                        'isProtected',
                        'permissions',
                        'createdAt',
                        'updatedAt',
                    ],
                ],
            ]);
    }

    public function test_index_returns_forbidden_for_unauthorized_user(): void
    {
        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->getJson('/api/v1/admin/roles');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have permission to manage roles',
            ]);
    }

    public function test_show_returns_single_role_for_authorized_user(): void
    {
        $role = Role::where('name', 'test-role')->first();

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->getJson("/api/v1/admin/roles/{$role->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'guardName',
                    'isProtected',
                    'permissions',
                    'createdAt',
                    'updatedAt',
                ],
            ]);
    }

    public function test_store_creates_new_role_for_authorized_user(): void
    {
        $roleData = [
            'name' => 'new-test-role',
            'permissions' => ['test_permission'],
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->postJson('/api/v1/admin/roles', $roleData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'guardName',
                    'isProtected',
                    'permissions',
                    'createdAt',
                    'updatedAt',
                ],
            ]);

        $this->assertDatabaseHas('roles', ['name' => 'new-test-role']);
    }

    public function test_store_validates_role_name_uniqueness(): void
    {
        $roleData = [
            'name' => 'test-role', // Already exists
            'permissions' => [],
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->postJson('/api/v1/admin/roles', $roleData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_prevents_creating_protected_role_names(): void
    {
        $roleData = [
            'name' => 'admin', // Protected name
            'permissions' => [],
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->postJson('/api/v1/admin/roles', $roleData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_modifies_existing_role_for_authorized_user(): void
    {
        // Create a fresh role for this test
        $role = Role::create(['name' => 'update-test-role']);

        $updateData = [
            'name' => 'updated-test-role',
            'permissions' => ['test_permission'],
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->putJson("/api/v1/admin/roles/{$role->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'guardName',
                    'isProtected',
                    'permissions',
                    'createdAt',
                    'updatedAt',
                ],
            ]);

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'updated-test-role']);
    }

    public function test_update_prevents_modifying_protected_roles(): void
    {
        $adminRole = Role::where('name', 'admin')->first();

        $updateData = [
            'name' => 'modified-admin',
            'permissions' => [],
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->putJson("/api/v1/admin/roles/{$adminRole->id}", $updateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_destroy_deletes_role_for_authorized_user(): void
    {
        // Create a fresh role for this test
        $role = Role::create(['name' => 'delete-test-role']);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->deleteJson("/api/v1/admin/roles/{$role->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Role deleted successfully',
            ]);

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_destroy_prevents_deleting_protected_roles(): void
    {
        $adminRole = Role::where('name', 'admin')->first();

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->deleteJson("/api/v1/admin/roles/{$adminRole->id}");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);

        $this->assertDatabaseHas('roles', ['id' => $adminRole->id]);
    }

    public function test_permissions_returns_all_permissions_for_authorized_user(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->getJson('/api/v1/admin/permissions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'guardName',
                        'group',
                        'description',
                        'createdAt',
                        'updatedAt',
                    ],
                ],
            ]);
    }

    public function test_permissions_returns_forbidden_for_unauthorized_user(): void
    {
        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->getJson('/api/v1/admin/permissions');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have permission to manage roles',
            ]);
    }
}
