<?php

namespace Tests\Unit\Services;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use App\Modules\Client\Actions\LogClientAuditAction;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Property-Based Tests for LogClientAuditAction
 *
 * Feature: client-portal-security, Property 9: Audit Logging
 * Validates: Requirements 16.1, 16.2, 16.3, 16.4, 16.5
 */
class AuditLogServicePropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    private LogClientAuditAction $auditLogService;

    private User $testUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditLogService = new LogClientAuditAction;

        // Create a test user for authentication
        $this->testUser = User::factory()->create();
        Auth::login($this->testUser);
    }

    protected function tearDown(): void
    {
        // Clean up audit logs for this test user to prevent interference
        AuditLog::where('user_id', $this->testUser->id)->delete();
        AuditLog::whereNull('user_id')->delete();

        parent::tearDown();
    }

    /**
     * Property 9: Audit Logging
     *
     * For any client authentication event, data access, or action (UI approval, invoice payment),
     * an audit log record SHALL be created. For any forbidden access attempt, an audit log SHALL
     * be created with details. For any audit log record, it SHALL contain: user_id, action,
     * auditable_type, auditable_id, ip_address, timestamp.
     *
     * **Validates: Requirements 16.1, 16.2, 16.3, 16.4, 16.5**
     */
    public function test_audit_logging_creates_complete_records(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\elements(['login', 'logout', 'ui_approval', 'invoice_payment', 'project_view', 'invoice_view']), // action
                Generator\elements(['127.0.0.1', '192.168.1.1', '10.0.0.1', '203.0.113.1']), // ip_address
                Generator\elements(['Mozilla/5.0', 'Chrome/91.0', 'Safari/14.0', 'Edge/91.0']) // user_agent
            )
            ->then(function ($action, $ipAddress, $userAgent) {
                // Create a test project for auditable model
                $project = Project::factory()->create();

                // Test general audit logging with explicit IP and user agent
                $auditLog = $this->auditLogService->log($action, $project, ['test_key' => 'test_value'], $ipAddress, $userAgent);

                // Verify audit log record contains all required fields
                $this->assertInstanceOf(AuditLog::class, $auditLog);
                $this->assertEquals($this->testUser->id, $auditLog->user_id);
                $this->assertEquals($action, $auditLog->action);
                $this->assertEquals(Project::class, $auditLog->auditable_type);
                $this->assertEquals($project->id, $auditLog->auditable_id);
                $this->assertEquals($ipAddress, $auditLog->ip_address);
                $this->assertEquals($userAgent, $auditLog->user_agent);
                $this->assertEquals(['test_key' => 'test_value'], $auditLog->metadata);
                $this->assertNotNull($auditLog->created_at);

                // Verify audit log is persisted in database
                $this->assertDatabaseHas('audit_logs', [
                    'user_id' => $this->testUser->id,
                    'action' => $action,
                    'auditable_type' => Project::class,
                    'auditable_id' => $project->id,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ]);
            });
    }

    /**
     * Property 9: Audit Logging - Forbidden Access Attempts
     *
     * For any forbidden access attempt, an audit log SHALL be created with details
     * including resource type and resource ID.
     *
     * **Validates: Requirements 16.4, 16.5**
     */
    public function test_audit_logging_forbidden_access_attempts(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\elements(['project', 'invoice', 'user', 'admin_panel']), // resource_type
                Generator\choose(1, 1000), // resource_id
                Generator\elements(['127.0.0.1', '192.168.1.100', '10.0.0.50']), // ip_address
                Generator\elements(['Test Browser', 'Chrome/91.0', 'Firefox/89.0']) // user_agent
            )
            ->then(function ($resourceType, $resourceId, $ipAddress, $userAgent) {
                // Clean up any existing forbidden access logs for this user
                AuditLog::where('user_id', $this->testUser->id)
                    ->where('action', 'forbidden_access_attempt')
                    ->delete();

                // Test forbidden access logging with explicit IP and user agent
                $this->auditLogService->logForbiddenAccess($resourceType, $resourceId, $ipAddress, $userAgent);

                // Verify forbidden access attempt is logged
                $this->assertDatabaseHas('audit_logs', [
                    'user_id' => $this->testUser->id,
                    'action' => 'forbidden_access_attempt',
                    'auditable_type' => null,
                    'auditable_id' => null,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ]);

                // Verify metadata contains resource details
                $auditLog = AuditLog::where('action', 'forbidden_access_attempt')
                    ->where('user_id', $this->testUser->id)
                    ->latest()
                    ->first();

                $this->assertNotNull($auditLog);
                $this->assertEquals($resourceType, $auditLog->metadata['resource_type']);
                $this->assertEquals($resourceId, $auditLog->metadata['resource_id']);
            });
    }

    /**
     * Property 9: Audit Logging - Authentication Events
     *
     * For any client authentication event, an audit log SHALL be created
     * with proper user identification and IP tracking.
     *
     * **Validates: Requirements 16.1, 16.2**
     */
    public function test_audit_logging_authentication_events(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\elements(['login_success', 'login_failed', 'logout', 'token_refresh']), // auth_event
                Generator\elements(['192.168.1.1', '10.0.0.1', '172.16.0.1']), // ip_address
                Generator\elements(['Chrome/91.0', 'Firefox/89.0', 'Safari/14.0']) // user_agent
            )
            ->then(function ($authEvent, $ipAddress, $userAgent) {
                // Test authentication event logging with explicit IP and user agent
                $this->auditLogService->logAuthentication($authEvent, $this->testUser->id, $ipAddress, $userAgent);

                // Verify authentication event is logged
                $this->assertDatabaseHas('audit_logs', [
                    'user_id' => $this->testUser->id,
                    'action' => $authEvent,
                    'auditable_type' => null,
                    'auditable_id' => null,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ]);

                // Verify audit log record is complete
                $auditLog = AuditLog::where('action', $authEvent)
                    ->where('user_id', $this->testUser->id)
                    ->latest()
                    ->first();

                $this->assertNotNull($auditLog);
                $this->assertEquals([], $auditLog->metadata);
                $this->assertNotNull($auditLog->created_at);
            });
    }

    /**
     * Property 9: Audit Logging - Null Auditable Model
     *
     * For any audit log without an auditable model, the system SHALL
     * handle null values correctly and still create complete records.
     *
     * **Validates: Requirements 16.5**
     */
    public function test_audit_logging_null_auditable_model(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\elements(['system_action', 'general_event', 'maintenance']), // action
                Generator\elements([[], ['key' => 'value'], ['multiple' => 'keys', 'test' => 123]]) // metadata
            )
            ->then(function ($action, $metadata) {
                // Test logging without auditable model with explicit IP and user agent
                $auditLog = $this->auditLogService->log($action, null, $metadata, '127.0.0.1', 'Test Agent');

                // Verify audit log record is created correctly
                $this->assertInstanceOf(AuditLog::class, $auditLog);
                $this->assertEquals($this->testUser->id, $auditLog->user_id);
                $this->assertEquals($action, $auditLog->action);
                $this->assertNull($auditLog->auditable_type);
                $this->assertNull($auditLog->auditable_id);
                $this->assertEquals('127.0.0.1', $auditLog->ip_address);
                $this->assertEquals('Test Agent', $auditLog->user_agent);
                $this->assertEquals($metadata, $auditLog->metadata);

                // Verify database record
                $this->assertDatabaseHas('audit_logs', [
                    'user_id' => $this->testUser->id,
                    'action' => $action,
                    'auditable_type' => null,
                    'auditable_id' => null,
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Test Agent',
                ]);
            });
    }

    /**
     * Property 9: Audit Logging - Multiple Actions Sequence
     *
     * For any sequence of audit actions, each SHALL be logged independently
     * with correct timestamps and maintain data integrity.
     *
     * **Validates: Requirements 16.1, 16.2, 16.3, 16.5**
     */
    public function test_audit_logging_multiple_actions_sequence(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\vector(3, Generator\elements(['view', 'create', 'update', 'delete', 'approve'])), // actions
                Generator\choose(1, 5) // number of projects to create
            )
            ->then(function ($actions, $projectCount) {
                // Clean up any existing audit logs for this user to ensure clean test
                AuditLog::where('user_id', $this->testUser->id)->delete();

                // Create multiple projects
                $projects = Project::factory()->count($projectCount)->create();

                $auditLogs = [];

                // Log multiple actions in sequence with explicit IP and user agent
                foreach ($actions as $index => $action) {
                    $project = $projects[$index % $projectCount];
                    $metadata = ['sequence' => $index, 'project_count' => $projectCount];

                    $auditLog = $this->auditLogService->log($action, $project, $metadata, '192.168.1.1', 'Sequence Test');
                    $auditLogs[] = $auditLog;

                    // Small delay to ensure different timestamps
                    usleep(1000);
                }

                // Verify all audit logs were created
                $this->assertCount(count($actions), $auditLogs);

                // Verify each audit log is independent and correct
                foreach ($auditLogs as $index => $auditLog) {
                    $expectedAction = $actions[$index];
                    $expectedProject = $projects[$index % $projectCount];

                    $this->assertEquals($this->testUser->id, $auditLog->user_id);
                    $this->assertEquals($expectedAction, $auditLog->action);
                    $this->assertEquals(Project::class, $auditLog->auditable_type);
                    $this->assertEquals($expectedProject->id, $auditLog->auditable_id);
                    $this->assertEquals($index, $auditLog->metadata['sequence']);
                    $this->assertEquals($projectCount, $auditLog->metadata['project_count']);
                }

                // Verify timestamps are in correct order
                for ($i = 1; $i < count($auditLogs); $i++) {
                    $this->assertGreaterThanOrEqual(
                        $auditLogs[$i - 1]->created_at,
                        $auditLogs[$i]->created_at,
                        'Audit log timestamps should be in chronological order'
                    );
                }

                // Verify database contains all records for this test
                $this->assertEquals(
                    count($actions),
                    AuditLog::where('user_id', $this->testUser->id)->count(),
                    'Database should contain all audit log records'
                );
            });
    }

    /**
     * Property 9: Audit Logging - Unauthenticated User Handling
     *
     * For any audit logging when no user is authenticated, the system SHALL
     * handle null user_id gracefully while still capturing other details.
     *
     * **Validates: Requirements 16.2, 16.5**
     */
    public function test_audit_logging_unauthenticated_user(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\elements(['failed_login', 'unauthorized_access', 'public_action']), // action
                Generator\elements(['10.0.0.1', '172.16.0.1', '203.0.113.1']) // ip_address
            )
            ->then(function ($action, $ipAddress) {
                // Logout to simulate unauthenticated state
                Auth::logout();

                // Test logging without authenticated user with explicit IP and user agent
                $auditLog = $this->auditLogService->log($action, null, ['unauthenticated' => true], $ipAddress, 'Unauthenticated Agent');

                // Verify audit log handles null user_id
                $this->assertInstanceOf(AuditLog::class, $auditLog);
                $this->assertNull($auditLog->user_id);
                $this->assertEquals($action, $auditLog->action);
                $this->assertNull($auditLog->auditable_type);
                $this->assertNull($auditLog->auditable_id);
                $this->assertEquals($ipAddress, $auditLog->ip_address);
                $this->assertEquals('Unauthenticated Agent', $auditLog->user_agent);
                $this->assertEquals(['unauthenticated' => true], $auditLog->metadata);

                // Verify database record
                $this->assertDatabaseHas('audit_logs', [
                    'user_id' => null,
                    'action' => $action,
                    'ip_address' => $ipAddress,
                    'user_agent' => 'Unauthenticated Agent',
                ]);
            });
    }
}
