<?php

namespace Tests\Feature;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected User $testUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions if they don't exist
        Permission::firstOrCreate(['name' => 'manage_internal_costs']);

        // Create admin user with manage_internal_costs permission
        $this->adminUser = User::factory()->create();
        $this->adminUser->givePermissionTo('manage_internal_costs');

        // Create regular user without permissions
        $this->regularUser = User::factory()->create();

        // Create test user to update costs for
        $this->testUser = User::factory()->create([
            'internal_hourly_cost' => 50.00,
        ]);
    }

    public function test_update_internal_cost_succeeds_for_authorized_user(): void
    {
        $costData = [
            'internalHourlyCost' => 75.50,
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->putJson("/api/v1/admin/users/{$this->testUser->id}/internal-cost", $costData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'userId',
                    'userName',
                    'updated',
                    'updatedAt',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Internal hourly cost updated successfully',
                'data' => [
                    'userId' => $this->testUser->id,
                    'userName' => $this->testUser->name,
                    'updated' => true,
                ],
            ]);

        // Verify the cost was updated in database
        $this->testUser->refresh();
        $this->assertEquals(75.50, $this->testUser->internal_hourly_cost);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'action' => 'update_internal_cost',
            'auditable_type' => User::class,
            'auditable_id' => $this->testUser->id,
        ]);
    }

    public function test_update_internal_cost_returns_forbidden_for_unauthorized_user(): void
    {
        $costData = [
            'internalHourlyCost' => 75.50,
        ];

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->putJson("/api/v1/admin/users/{$this->testUser->id}/internal-cost", $costData);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have permission to manage internal costs',
            ]);

        // Verify the cost was NOT updated
        $this->testUser->refresh();
        $this->assertEquals(50.00, $this->testUser->internal_hourly_cost);
    }

    public function test_update_internal_cost_validates_positive_cost(): void
    {
        $costData = [
            'internalHourlyCost' => -10.00,
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->putJson("/api/v1/admin/users/{$this->testUser->id}/internal-cost", $costData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['internalHourlyCost']);
    }

    public function test_update_internal_cost_validates_required_cost(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->putJson("/api/v1/admin/users/{$this->testUser->id}/internal-cost", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['internalHourlyCost']);
    }

    public function test_update_internal_cost_returns_not_found_for_invalid_user(): void
    {
        $costData = [
            'internalHourlyCost' => 75.50,
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->putJson('/api/v1/admin/users/99999/internal-cost', $costData);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'User not found',
            ]);
    }

    public function test_bulk_update_internal_costs_succeeds_for_authorized_user(): void
    {
        $user2 = User::factory()->create(['internal_hourly_cost' => 60.00]);
        $user3 = User::factory()->create(['internal_hourly_cost' => 70.00]);

        $bulkData = [
            'users' => [
                [
                    'userId' => $this->testUser->id,
                    'internalHourlyCost' => 80.00,
                ],
                [
                    'userId' => $user2->id,
                    'internalHourlyCost' => 90.00,
                ],
                [
                    'userId' => $user3->id,
                    'internalHourlyCost' => 100.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->putJson('/api/v1/admin/users/internal-costs', $bulkData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'totalUpdated',
                    'updatedAt',
                    'updatedUsers' => [
                        '*' => [
                            'userId',
                            'userName',
                            'updated',
                        ],
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Bulk internal costs updated successfully',
                'data' => [
                    'totalUpdated' => 3,
                ],
            ]);

        // Verify all costs were updated
        $this->testUser->refresh();
        $user2->refresh();
        $user3->refresh();

        $this->assertEquals(80.00, $this->testUser->internal_hourly_cost);
        $this->assertEquals(90.00, $user2->internal_hourly_cost);
        $this->assertEquals(100.00, $user3->internal_hourly_cost);

        // Verify audit logs were created for all users
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'action' => 'bulk_update_internal_cost',
            'auditable_type' => User::class,
            'auditable_id' => $this->testUser->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'action' => 'bulk_update_internal_cost',
            'auditable_type' => User::class,
            'auditable_id' => $user2->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'action' => 'bulk_update_internal_cost',
            'auditable_type' => User::class,
            'auditable_id' => $user3->id,
        ]);
    }

    public function test_bulk_update_internal_costs_returns_forbidden_for_unauthorized_user(): void
    {
        $bulkData = [
            'users' => [
                [
                    'userId' => $this->testUser->id,
                    'internalHourlyCost' => 80.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->putJson('/api/v1/admin/users/internal-costs', $bulkData);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have permission to manage internal costs',
            ]);

        // Verify the cost was NOT updated
        $this->testUser->refresh();
        $this->assertEquals(50.00, $this->testUser->internal_hourly_cost);
    }

    public function test_bulk_update_internal_costs_validates_users_array(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->putJson('/api/v1/admin/users/internal-costs', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['users']);
    }

    public function test_bulk_update_internal_costs_validates_individual_user_data(): void
    {
        $bulkData = [
            'users' => [
                [
                    'userId' => 'invalid',
                    'internalHourlyCost' => -10.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->putJson('/api/v1/admin/users/internal-costs', $bulkData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'users.0.userId',
                'users.0.internalHourlyCost',
            ]);
    }

    public function test_bulk_update_internal_costs_validates_user_exists(): void
    {
        $bulkData = [
            'users' => [
                [
                    'userId' => 99999,
                    'internalHourlyCost' => 75.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->putJson('/api/v1/admin/users/internal-costs', $bulkData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['users.0.userId']);
    }

    public function test_response_does_not_expose_actual_cost_values(): void
    {
        $costData = [
            'internalHourlyCost' => 75.50,
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->putJson("/api/v1/admin/users/{$this->testUser->id}/internal-cost", $costData);

        $response->assertStatus(200);

        // Ensure the response does not contain the actual cost value
        $responseData = $response->json();
        $this->assertArrayNotHasKey('internalHourlyCost', $responseData['data']);
        $this->assertArrayNotHasKey('cost', $responseData['data']);
        $this->assertArrayNotHasKey('internal_hourly_cost', $responseData['data']);
    }

    public function test_audit_log_contains_proper_metadata(): void
    {
        $costData = [
            'internalHourlyCost' => 85.75,
        ];

        $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders(['Accept-Language' => 'en'])
            ->putJson("/api/v1/admin/users/{$this->testUser->id}/internal-cost", $costData);

        $auditLog = AuditLog::where([
            'action' => 'update_internal_cost',
            'auditable_type' => User::class,
            'auditable_id' => $this->testUser->id,
        ])->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals($this->adminUser->id, $auditLog->user_id);

        $metadata = $auditLog->metadata;
        $this->assertArrayHasKey('old_cost', $metadata);
        $this->assertArrayHasKey('new_cost', $metadata);
        $this->assertArrayHasKey('updated_by', $metadata);
        $this->assertArrayHasKey('updated_by_id', $metadata);

        $this->assertEquals('50.00', $metadata['old_cost']);
        $this->assertEquals('85.75', $metadata['new_cost']);
        $this->assertEquals($this->adminUser->name, $metadata['updated_by']);
        $this->assertEquals($this->adminUser->id, $metadata['updated_by_id']);
    }
}
