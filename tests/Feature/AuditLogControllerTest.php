<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditLogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create permissions
        Permission::create(['name' => 'view_audit_logs']);
        
        // Create roles
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo('view_audit_logs');
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/admin/audit-logs');
        
        $response->assertStatus(401);
    }

    public function test_index_requires_permission(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        
        $response = $this->getJson('/api/v1/admin/audit-logs');
        
        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'message' => 'You do not have permission to view audit logs'
                 ]);
    }

    public function test_index_returns_audit_logs_with_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Sanctum::actingAs($user);
        
        // Create some audit logs
        $auditLog1 = AuditLog::create([
            'user_id' => $user->id,
            'action' => 'update_internal_cost',
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => 1,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Test Agent',
            'metadata' => ['old_cost' => '50.00', 'new_cost' => '75.00']
        ]);
        
        $auditLog2 = AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'ip_address' => '192.168.1.2',
            'user_agent' => 'Test Agent 2',
            'metadata' => []
        ]);
        
        $response = $this->getJson('/api/v1/admin/audit-logs');
        
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Audit logs retrieved successfully'
                 ])
                 ->assertJsonStructure([
                     'data' => [
                         'auditLogs' => [
                             '*' => [
                                 'auditLogId',
                                 'userId',
                                 'userName',
                                 'action',
                                 'auditableType',
                                 'auditableId',
                                 'ipAddress',
                                 'userAgent',
                                 'metadata',
                                 'createdAt',
                                 'isSensitive'
                             ]
                         ],
                         'pagination' => [
                             'total',
                             'count',
                             'perPage',
                             'currentPage',
                             'totalPages'
                         ]
                     ]
                 ]);
        
        // Verify the audit logs are returned
        $data = $response->json('data.auditLogs');
        $this->assertCount(2, $data);
        
        // Verify both actions are present
        $actions = collect($data)->pluck('action')->toArray();
        $this->assertContains('update_internal_cost', $actions);
        $this->assertContains('login', $actions);
    }

    public function test_index_filters_by_action(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Sanctum::actingAs($user);
        
        // Create audit logs with different actions
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'update_internal_cost',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Test Agent',
            'metadata' => []
        ]);
        
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'ip_address' => '192.168.1.2',
            'user_agent' => 'Test Agent',
            'metadata' => []
        ]);
        
        $response = $this->getJson('/api/v1/admin/audit-logs?filter[action]=update_internal_cost');
        
        $response->assertStatus(200);
        $data = $response->json('data.auditLogs');
        $this->assertCount(1, $data);
        $this->assertEquals('update_internal_cost', $data[0]['action']);
    }

    public function test_index_filters_by_user_id(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user1->assignRole('admin');
        Sanctum::actingAs($user1);
        
        // Create audit logs for different users
        AuditLog::create([
            'user_id' => $user1->id,
            'action' => 'login',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Test Agent',
            'metadata' => []
        ]);
        
        AuditLog::create([
            'user_id' => $user2->id,
            'action' => 'login',
            'ip_address' => '192.168.1.2',
            'user_agent' => 'Test Agent',
            'metadata' => []
        ]);
        
        $response = $this->getJson("/api/v1/admin/audit-logs?filter[user_id]={$user2->id}");
        
        $response->assertStatus(200);
        $data = $response->json('data.auditLogs');
        $this->assertCount(1, $data);
        $this->assertEquals($user2->id, $data[0]['userId']);
    }

    public function test_index_searches_by_ip_address(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Sanctum::actingAs($user);
        
        // Create audit logs with different IP addresses
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Test Agent',
            'metadata' => []
        ]);
        
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Test Agent',
            'metadata' => []
        ]);
        
        $response = $this->getJson('/api/v1/admin/audit-logs?filter[search]=192.168');
        
        $response->assertStatus(200);
        $data = $response->json('data.auditLogs');
        $this->assertCount(1, $data);
        $this->assertStringStartsWith('192.168', $data[0]['ipAddress']);
    }

    public function test_index_validates_invalid_action(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Sanctum::actingAs($user);
        
        $response = $this->getJson('/api/v1/admin/audit-logs?filter[action]=invalid_action');
        
        $response->assertStatus(422)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Invalid filter parameters'
                 ]);
    }

    public function test_index_marks_sensitive_actions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Sanctum::actingAs($user);
        
        // Create sensitive and non-sensitive audit logs
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'update_internal_cost', // Sensitive
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Test Agent',
            'metadata' => []
        ]);
        
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login', // Not sensitive
            'ip_address' => '192.168.1.2',
            'user_agent' => 'Test Agent',
            'metadata' => []
        ]);
        
        $response = $this->getJson('/api/v1/admin/audit-logs');
        
        $response->assertStatus(200);
        $data = $response->json('data.auditLogs');
        
        // Find the sensitive and non-sensitive logs
        $sensitiveLog = collect($data)->firstWhere('action', 'update_internal_cost');
        $nonSensitiveLog = collect($data)->firstWhere('action', 'login');
        
        $this->assertTrue($sensitiveLog['isSensitive']);
        $this->assertFalse($nonSensitiveLog['isSensitive']);
    }
}