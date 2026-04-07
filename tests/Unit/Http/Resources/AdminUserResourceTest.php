<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\V1\Admin\AdminUserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Simple Unit Tests for AdminUserResource
 *
 * Validates: Requirements 2.4, 2.6
 */
class AdminUserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions if they don't exist
        Permission::firstOrCreate(['name' => 'view_internal_costs']);
        Permission::firstOrCreate(['name' => 'view_admin_stats']);

        // Create roles if they don't exist
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'developer']);
    }

    /**
     * Test that AdminUserResource returns all required fields in camelCase format.
     */
    public function test_admin_user_resource_returns_camel_case_fields(): void
    {
        // Create a user with internal cost
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'internal_hourly_cost' => 75.50,
            'skills' => ['PHP', 'Laravel', 'JavaScript'],
        ]);

        // Assign role
        $user->assignRole('developer');

        // Create authenticated user with permission
        $authUser = User::factory()->create();
        $authUser->givePermissionTo('view_internal_costs');

        // Create request with authenticated user
        $request = Request::create('/test');
        $request->setUserResolver(fn () => $authUser);

        // Transform using AdminUserResource
        $resource = new AdminUserResource($user);
        $response = $resource->toArray($request);

        // Verify all expected camelCase keys exist
        $expectedKeys = [
            'userId', 'name', 'email', 'emailVerifiedAt', 'avatar',
            'skills', 'internalHourlyCost', 'roles', 'createdAt', 'updatedAt',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $response,
                "AdminUserResource should contain camelCase key: {$key}");
        }

        // Verify no snake_case keys exist
        $forbiddenKeys = [
            'user_id', 'email_verified_at', 'internal_hourly_cost',
            'created_at', 'updated_at',
        ];

        foreach ($forbiddenKeys as $key) {
            $this->assertArrayNotHasKey($key, $response,
                "AdminUserResource should NOT contain snake_case key: {$key}");
        }

        // Verify data integrity
        $this->assertEquals($user->id, $response['userId']);
        $this->assertEquals($user->name, $response['name']);
        $this->assertEquals($user->email, $response['email']);
        $this->assertEquals($user->skills, $response['skills']);
        $this->assertEquals(75.50, $response['internalHourlyCost']);
        $this->assertEquals(['developer'], $response['roles']);
        $this->assertEquals($user->created_at->toISOString(), $response['createdAt']);
        $this->assertEquals($user->updated_at->toISOString(), $response['updatedAt']);
    }

    /**
     * Test that internal cost is hidden when user lacks permission.
     */
    public function test_internal_cost_hidden_without_permission(): void
    {
        // Create a user with internal cost
        $user = User::factory()->create([
            'internal_hourly_cost' => 75.50,
        ]);

        // Create authenticated user WITHOUT permission
        $authUser = User::factory()->create();
        // Note: Not giving view_internal_costs permission

        // Create request with authenticated user
        $request = Request::create('/test');
        $request->setUserResolver(fn () => $authUser);

        // Transform using AdminUserResource
        $resource = new AdminUserResource($user);
        $response = $resource->toArray($request);

        // Verify internal cost is NOT included
        $this->assertArrayNotHasKey('internalHourlyCost', $response,
            'Internal cost should be hidden when user lacks view_internal_costs permission');
    }

    /**
     * Test that internal cost is visible when user has permission.
     */
    public function test_internal_cost_visible_with_permission(): void
    {
        // Create a user with internal cost
        $user = User::factory()->create([
            'internal_hourly_cost' => 125.75,
        ]);

        // Create authenticated user WITH permission
        $authUser = User::factory()->create();
        $authUser->givePermissionTo('view_internal_costs');

        // Create request with authenticated user
        $request = Request::create('/test');
        $request->setUserResolver(fn () => $authUser);

        // Transform using AdminUserResource
        $resource = new AdminUserResource($user);
        $response = $resource->toArray($request);

        // Verify internal cost is included and correct
        $this->assertArrayHasKey('internalHourlyCost', $response,
            'Internal cost should be visible when user has view_internal_costs permission');
        $this->assertEquals(125.75, $response['internalHourlyCost']);
    }

    /**
     * Test that null internal cost returns 0.0 when visible.
     */
    public function test_null_internal_cost_returns_zero(): void
    {
        // Create a user with null internal cost
        $user = User::factory()->create([
            'internal_hourly_cost' => null,
        ]);

        // Create authenticated user WITH permission
        $authUser = User::factory()->create();
        $authUser->givePermissionTo('view_internal_costs');

        // Create request with authenticated user
        $request = Request::create('/test');
        $request->setUserResolver(fn () => $authUser);

        // Transform using AdminUserResource
        $resource = new AdminUserResource($user);
        $response = $resource->toArray($request);

        // Verify null internal cost returns 0.0
        $this->assertArrayHasKey('internalHourlyCost', $response);
        $this->assertEquals(0.0, $response['internalHourlyCost']);
    }

    /**
     * Test that resource handles users without roles.
     */
    public function test_resource_handles_users_without_roles(): void
    {
        // Create a user without roles
        $user = User::factory()->create();

        // Create authenticated user WITH permission
        $authUser = User::factory()->create();
        $authUser->givePermissionTo('view_internal_costs');

        // Create request with authenticated user
        $request = Request::create('/test');
        $request->setUserResolver(fn () => $authUser);

        // Transform using AdminUserResource
        $resource = new AdminUserResource($user);
        $response = $resource->toArray($request);

        // Verify roles is empty array
        $this->assertArrayHasKey('roles', $response);
        $this->assertEquals([], $response['roles']);
    }
}
