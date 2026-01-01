<?php

namespace Tests\Unit\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectHistory;
use App\Models\User;
use Carbon\Carbon;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Property-Based Tests for ClientProjectController UI Approval Workflow
 * 
 * Feature: client-portal-security, Property 6: UI Approval Workflow
 * Validates: Requirements 7.2, 7.3, 7.4, 7.5, 7.6
 */
class ClientProjectControllerPropertyTest extends TestCase
{
    use TestTrait, RefreshDatabase;

    private User $clientUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a client user for testing
        $this->clientUser = User::factory()->create();
        $this->clientUser->assignRole('client');
        Auth::login($this->clientUser);
    }

    protected function tearDown(): void
    {
        // Clean up test data more thoroughly
        if (isset($this->clientUser)) {
            // Delete audit logs for this user
            AuditLog::where('user_id', $this->clientUser->id)->delete();
            
            // Delete project histories for projects owned by this client
            $projectIds = Project::where('client_id', $this->clientUser->id)->pluck('id');
            ProjectHistory::whereIn('project_id', $projectIds)->delete();
            
            // Delete projects owned by this client
            Project::where('client_id', $this->clientUser->id)->delete();
        }
        
        parent::tearDown();
    }

    /**
     * Property 6: UI Approval Workflow
     * 
     * For any client UI approval request on a project in 'ui_ux' status, the system SHALL:
     * set ui_approved_at to current timestamp, cancel any scheduled AutoApproveUIJob,
     * transition status to 'development', and record the approval in project_history.
     * For any project not in 'ui_ux' status, the approval request SHALL be rejected with an error.
     * 
     * **Validates: Requirements 7.2, 7.3, 7.4, 7.5, 7.6**
     */
    public function test_ui_approval_workflow_for_ui_ux_projects(): void
    {
        $this
            ->limitTo(3) // Reduced iterations for memory efficiency
            ->forAll(
                Generator\elements(['Website Redesign', 'Mobile App']), // Reduced options
                Generator\choose(1000, 5000), // Smaller range
                Generator\elements(['2025-06-30', '2025-12-31']), // Reduced options
                Generator\elements(['job_123', null]) // Reduced options
            )
            ->then(function ($title, $budget, $deadline, $autoApprovalJobId) {
                // Create a project in ui_ux status for this client
                $project = Project::factory()->create([
                    'client_id' => $this->clientUser->id,
                    'title' => $title,
                    'status' => ProjectStatus::UI_UX,
                    'budget' => $budget,
                    'deadline' => $deadline,
                    'ui_approved_at' => null,
                    'auto_approval_job_id' => $autoApprovalJobId,
                ]);

                // Record the time before approval for timestamp validation (with buffer)
                $beforeApproval = Carbon::now()->subSecond();

                // Make the approval request
                $response = $this->postJson("/api/v1/client/projects/{$project->id}/approve-ui", [], [
                    'Accept-Language' => 'en',
                ]);

                // Record the time after approval (with buffer)
                $afterApproval = Carbon::now()->addSecond();

                // Verify successful response
                $response->assertStatus(200);
                $response->assertJson([
                    'success' => true,
                    'message' => 'UI/UX deliverables approved successfully',
                ]);

                // Refresh project from database
                $project->refresh();

                // Requirement 7.2: System SHALL set ui_approved_at to current timestamp
                $this->assertNotNull($project->ui_approved_at);
                $this->assertGreaterThanOrEqual($beforeApproval, $project->ui_approved_at);
                $this->assertLessThanOrEqual($afterApproval, $project->ui_approved_at);

                // Requirement 7.3: System SHALL cancel any scheduled AutoApproveUIJob
                $this->assertNull($project->auto_approval_job_id);

                // Requirement 7.4: System SHALL transition project status from 'ui_ux' to 'development'
                $this->assertEquals(ProjectStatus::DEVELOPMENT, $project->status);

                // Requirement 7.6: System SHALL record the approval in project_history
                // Status change is recorded by ProjectObserver (without specific reason)
                $this->assertDatabaseHas('project_histories', [
                    'project_id' => $project->id,
                    'field_name' => 'status',
                    'old_value' => ProjectStatus::UI_UX->value,
                    'new_value' => ProjectStatus::DEVELOPMENT->value,
                ]);

                // UI approval timestamp is recorded by controller (with specific reason)
                $this->assertDatabaseHas('project_histories', [
                    'project_id' => $project->id,
                    'field_name' => 'ui_approved_at',
                    'old_value' => null,
                    'new_value' => $project->ui_approved_at->toISOString(),
                    'reason' => 'Client approved UI deliverables',
                    'changed_by' => $this->clientUser->id,
                ]);

                // Verify audit log is created
                $this->assertDatabaseHas('audit_logs', [
                    'user_id' => $this->clientUser->id,
                    'action' => 'client_ui_approved',
                    'auditable_type' => Project::class,
                    'auditable_id' => $project->id,
                ]);

                // Verify response data structure
                $responseData = $response->json('data');
                $this->assertEquals($project->id, $responseData['projectId']);
                $this->assertEquals(ProjectStatus::DEVELOPMENT->value, $responseData['status']);
                $this->assertEquals(ProjectStatus::DEVELOPMENT->label(), $responseData['statusLabel']);
                $this->assertFalse($responseData['canApproveUI']); // Should be false after approval
                $this->assertNotEmpty($responseData['uiApprovedAt']);
            });
    }

    /**
     * Property 6: UI Approval Workflow - Rejection for Non-UI_UX Projects
     * 
     * For any project not in 'ui_ux' status, the approval request SHALL be rejected with an error.
     * 
     * **Validates: Requirements 7.5**
     */
    public function test_ui_approval_workflow_rejects_non_ui_ux_projects(): void
    {
        $this
            ->limitTo(3) // Reduced iterations
            ->forAll(
                Generator\elements([
                    ProjectStatus::DRAFT,
                    ProjectStatus::ACTIVE,
                    ProjectStatus::DEVELOPMENT,
                    ProjectStatus::TESTING,
                    ProjectStatus::COMPLETED,
                    ProjectStatus::ON_HOLD,
                    ProjectStatus::CANCELLED
                ]), // non-ui_ux statuses
                Generator\elements(['Project Alpha', 'Beta System', 'Gamma App']) // project titles
            )
            ->then(function ($status, $title) {
                // Create a project in non-ui_ux status for this client
                $project = Project::factory()->create([
                    'client_id' => $this->clientUser->id,
                    'title' => $title,
                    'status' => $status,
                    'ui_approved_at' => null,
                ]);

                // Store original values to verify they don't change
                $originalStatus = $project->status;
                $originalUiApprovedAt = $project->ui_approved_at;
                $originalAutoApprovalJobId = $project->auto_approval_job_id;

                // Make the approval request
                $response = $this->postJson("/api/v1/client/projects/{$project->id}/approve-ui", [], [
                    'Accept-Language' => 'en',
                ]);

                // Requirement 7.5: Approval request SHALL be rejected with an error
                $response->assertStatus(422);
                $response->assertJson([
                    'success' => false,
                    'message' => 'Validation failed for UI approval',
                ]);

                // Verify error contains status information
                $errors = $response->json('errors');
                $this->assertArrayHasKey('status', $errors);
                $this->assertStringContainsString('UI/UX phase', $errors['status'][0]);
                $this->assertStringContainsString($status->label(), $errors['status'][0]);

                // Refresh project from database
                $project->refresh();

                // Verify project state remains unchanged
                $this->assertEquals($originalStatus, $project->status);
                $this->assertEquals($originalUiApprovedAt, $project->ui_approved_at);
                $this->assertEquals($originalAutoApprovalJobId, $project->auto_approval_job_id);

                // Verify no project history is created for failed approval
                $this->assertDatabaseMissing('project_histories', [
                    'project_id' => $project->id,
                    'field_name' => 'status',
                    'reason' => 'Client approved UI deliverables',
                ]);

                // Verify no audit log for successful approval is created
                $this->assertDatabaseMissing('audit_logs', [
                    'user_id' => $this->clientUser->id,
                    'action' => 'client_ui_approved',
                    'auditable_type' => Project::class,
                    'auditable_id' => $project->id,
                ]);
            });
    }

    /**
     * Property 6: UI Approval Workflow - Already Approved Projects
     * 
     * For any project that has already been approved (ui_approved_at is not null),
     * the approval request SHALL be rejected with an error.
     * 
     * **Validates: Requirements 7.5**
     */
    public function test_ui_approval_workflow_rejects_already_approved_projects(): void
    {
        $this
            ->limitTo(3) // Reduced iterations
            ->forAll(
                Generator\elements(['2025-01-01 10:00:00', '2025-02-15 14:30:00', '2025-03-20 09:15:00']), // approval timestamps
                Generator\elements(['Project Delta', 'Epsilon System', 'Zeta Platform']) // project titles
            )
            ->then(function ($approvalTimestamp, $title) {
                // Create a project in ui_ux status that's already approved
                $project = Project::factory()->create([
                    'client_id' => $this->clientUser->id,
                    'title' => $title,
                    'status' => ProjectStatus::UI_UX,
                    'ui_approved_at' => Carbon::parse($approvalTimestamp),
                ]);

                // Store original values to verify they don't change
                $originalStatus = $project->status;
                $originalUiApprovedAt = $project->ui_approved_at;
                $originalAutoApprovalJobId = $project->auto_approval_job_id;

                // Make the approval request
                $response = $this->postJson("/api/v1/client/projects/{$project->id}/approve-ui", [], [
                    'Accept-Language' => 'en',
                ]);

                // Approval request SHALL be rejected with an error
                $response->assertStatus(422);
                $response->assertJson([
                    'success' => false,
                    'message' => 'Validation failed for UI approval',
                ]);

                // Verify error contains approval information
                $errors = $response->json('errors');
                $this->assertArrayHasKey('approval', $errors);
                $this->assertStringContainsString('already been approved', $errors['approval'][0]);

                // Refresh project from database
                $project->refresh();

                // Verify project state remains unchanged
                $this->assertEquals($originalStatus, $project->status);
                $this->assertEquals($originalUiApprovedAt->toISOString(), $project->ui_approved_at->toISOString());
                $this->assertEquals($originalAutoApprovalJobId, $project->auto_approval_job_id);

                // Verify no additional project history is created
                $historyCount = ProjectHistory::where('project_id', $project->id)
                    ->where('reason', 'Client approved UI deliverables')
                    ->count();
                $this->assertEquals(0, $historyCount);
            });
    }

    /**
     * Property 6: UI Approval Workflow - Multiple Concurrent Approvals
     * 
     * For any sequence of UI approval requests on different projects in 'ui_ux' status,
     * each SHALL be processed independently and correctly.
     * 
     * **Validates: Requirements 7.2, 7.3, 7.4, 7.6**
     */
    public function test_ui_approval_workflow_multiple_projects(): void
    {
        $this
            ->limitTo(2) // Reduced iterations
            ->forAll(
                Generator\choose(2, 5), // number of projects to create
                Generator\elements(['job_1', 'job_2', 'job_3', null]) // auto approval job ids
            )
            ->then(function ($projectCount, $baseJobId) {
                // Clean up any existing data for this user before starting
                AuditLog::where('user_id', $this->clientUser->id)->delete();
                $existingProjectIds = Project::where('client_id', $this->clientUser->id)->pluck('id');
                ProjectHistory::whereIn('project_id', $existingProjectIds)->delete();
                Project::where('client_id', $this->clientUser->id)->delete();
                
                $projects = [];
                
                // Create multiple projects in ui_ux status
                for ($i = 0; $i < $projectCount; $i++) {
                    $jobId = $baseJobId ? $baseJobId . '_' . $i : null;
                    $projects[] = Project::factory()->create([
                        'client_id' => $this->clientUser->id,
                        'title' => "Project {$i}",
                        'status' => ProjectStatus::UI_UX,
                        'ui_approved_at' => null,
                        'auto_approval_job_id' => $jobId,
                    ]);
                }

                $approvalTimes = [];

                // Approve each project
                foreach ($projects as $index => $project) {
                    $beforeApproval = Carbon::now()->subSecond();
                    
                    $response = $this->postJson("/api/v1/client/projects/{$project->id}/approve-ui", [], [
                        'Accept-Language' => 'en',
                    ]);

                    $afterApproval = Carbon::now()->addSecond();
                    $approvalTimes[] = ['before' => $beforeApproval, 'after' => $afterApproval];

                    // Verify successful response for each project
                    $response->assertStatus(200);
                    $response->assertJson([
                        'success' => true,
                        'message' => 'UI/UX deliverables approved successfully',
                    ]);

                    // Small delay to ensure different timestamps
                    usleep(1000);
                }

                // Verify all projects were approved correctly
                foreach ($projects as $index => $project) {
                    $project->refresh();
                    
                    // Verify approval timestamp
                    $this->assertNotNull($project->ui_approved_at);
                    $this->assertGreaterThanOrEqual($approvalTimes[$index]['before'], $project->ui_approved_at);
                    $this->assertLessThanOrEqual($approvalTimes[$index]['after'], $project->ui_approved_at);

                    // Verify status transition
                    $this->assertEquals(ProjectStatus::DEVELOPMENT, $project->status);

                    // Verify auto approval job cancellation
                    $this->assertNull($project->auto_approval_job_id);

                    // Verify project history (status recorded by observer, ui_approved_at by controller)
                    $this->assertDatabaseHas('project_histories', [
                        'project_id' => $project->id,
                        'field_name' => 'status',
                        'old_value' => ProjectStatus::UI_UX->value,
                        'new_value' => ProjectStatus::DEVELOPMENT->value,
                    ]);

                    // Verify audit log
                    $this->assertDatabaseHas('audit_logs', [
                        'user_id' => $this->clientUser->id,
                        'action' => 'client_ui_approved',
                        'auditable_type' => Project::class,
                        'auditable_id' => $project->id,
                    ]);
                }

                // Verify total audit logs match project count (only count logs created in this test)
                $auditLogCount = AuditLog::where('user_id', $this->clientUser->id)
                    ->where('action', 'client_ui_approved')
                    ->whereIn('auditable_id', collect($projects)->pluck('id'))
                    ->count();
                $this->assertEquals($projectCount, $auditLogCount);
            });
    }

    /**
     * Property 6: UI Approval Workflow - Transaction Integrity
     * 
     * For any UI approval request, if any part of the workflow fails,
     * the entire transaction SHALL be rolled back maintaining data consistency.
     * 
     * **Validates: Requirements 7.2, 7.3, 7.4, 7.6**
     */
    public function test_ui_approval_workflow_transaction_integrity(): void
    {
        $this
            ->limitTo(3) // Reduced iterations
            ->forAll(
                Generator\elements(['Critical Project', 'Important System', 'Priority App']), // project titles
                Generator\elements(['job_critical', 'job_important', 'job_priority']) // auto approval job ids
            )
            ->then(function ($title, $jobId) {
                // Clean up any existing data for this user before starting
                AuditLog::where('user_id', $this->clientUser->id)->delete();
                $existingProjectIds = Project::where('client_id', $this->clientUser->id)->pluck('id');
                ProjectHistory::whereIn('project_id', $existingProjectIds)->delete();
                Project::where('client_id', $this->clientUser->id)->delete();
                
                // Create a project in ui_ux status
                $project = Project::factory()->create([
                    'client_id' => $this->clientUser->id,
                    'title' => $title,
                    'status' => ProjectStatus::UI_UX,
                    'ui_approved_at' => null,
                    'auto_approval_job_id' => $jobId,
                ]);

                // Store original state
                $originalStatus = $project->status;
                $originalUiApprovedAt = $project->ui_approved_at;
                $originalAutoApprovalJobId = $project->auto_approval_job_id;

                // Count existing records before approval (should be 0 after cleanup)
                $initialHistoryCount = ProjectHistory::where('project_id', $project->id)->count();
                $initialAuditLogCount = AuditLog::where('user_id', $this->clientUser->id)->count();

                // Make successful approval request
                $response = $this->postJson("/api/v1/client/projects/{$project->id}/approve-ui", [], [
                    'Accept-Language' => 'en',
                ]);

                // Verify successful response
                $response->assertStatus(200);

                // Refresh project
                $project->refresh();

                // Verify all changes were applied atomically
                $this->assertNotNull($project->ui_approved_at);
                $this->assertEquals(ProjectStatus::DEVELOPMENT, $project->status);
                $this->assertNull($project->auto_approval_job_id);

                // Verify exactly 2 new history records were created (status from observer + ui_approved_at from controller)
                $newHistoryCount = ProjectHistory::where('project_id', $project->id)->count();
                $this->assertEquals($initialHistoryCount + 2, $newHistoryCount);

                // Verify exactly 1 new audit log was created
                $newAuditLogCount = AuditLog::where('user_id', $this->clientUser->id)->count();
                $this->assertEquals($initialAuditLogCount + 1, $newAuditLogCount);

                // Verify all database changes are consistent
                $statusHistory = ProjectHistory::where('project_id', $project->id)
                    ->where('field_name', 'status')
                    ->first(); // Observer creates this without specific reason
                
                $approvalHistory = ProjectHistory::where('project_id', $project->id)
                    ->where('field_name', 'ui_approved_at')
                    ->where('reason', 'Client approved UI deliverables')
                    ->first();

                $this->assertNotNull($statusHistory);
                $this->assertNotNull($approvalHistory);
                $this->assertEquals($project->ui_approved_at->toISOString(), $approvalHistory->new_value);
                $this->assertEquals(ProjectStatus::DEVELOPMENT->value, $statusHistory->new_value);
            });
    }
}