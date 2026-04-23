<?php

namespace Tests\Unit\Http\Resources;

use App\Modules\Client\Resources\ClientProjectResource;
use App\Models\Invoice;
use App\Modules\Project\Models\Project;
use App\Models\User;
use App\Modules\Client\Actions\CalculateProjectProgressAction;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Tests for ClientProjectResource Black Box
 *
 * Feature: client-portal-security, Property 3: ClientProjectResource Black Box
 * Validates: Requirements 4.1, 4.2, 4.3, 4.5
 */
class ClientProjectResourcePropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    /**
     * Property 3: ClientProjectResource Black Box
     *
     * For any API response using ClientProjectResource, the response SHALL contain only:
     * projectId, title, status, statusLabel, progressPercentage, deadline, budget,
     * currency, uiApprovedAt, canApproveUI, createdAt, invoices. The response SHALL NOT
     * contain: description, tasks, developers, wikis, timeEntries, internalNotes,
     * or any user information except the client's own profile.
     *
     * **Validates: Requirements 4.1, 4.2, 4.3, 4.5**
     */
    public function test_client_project_resource_black_box_format(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\names(), // title
                Generator\elements(['draft', 'active', 'ui_ux', 'development', 'testing', 'completed', 'on_hold', 'cancelled']), // status
                Generator\choose(100, 10000), // budget
                Generator\bool() // has ui_approved_at
            )
            ->then(function ($title, $status, $budget, $hasUiApproval) {
                // Ensure we have valid data
                $title = $title ?: 'Test Project';

                // Create required models
                $client = User::factory()->create();
                $clientProfile = $this->ensureClientProfile($client);
                $creator = User::factory()->create();

                // Create project
                $project = Project::factory()->create([
                    'client_id' => $clientProfile->id,
                    'title' => $title,
                    'status' => $status,
                    'budget' => $budget,
                    'ui_approved_at' => $hasUiApproval ? now() : null,
                    'created_by' => $creator->id,
                    'updated_by' => $creator->id,
                ]);

                // Create some invoices for the project with unique invoice numbers
                $invoices = collect();
                for ($i = 0; $i < 2; $i++) {
                    $invoices->push(Invoice::factory()->create([
                        'project_id' => $project->id,
                        'invoice_number' => 'INV-TEST-'.uniqid().'-'.$i,
                        'created_by' => $creator->id,
                    ]));
                }

                // Load invoices relationship
                $project->load('invoices');

                // Transform using ClientProjectResource
                $resource = new ClientProjectResource($project);
                $response = $resource->toArray(request());

                // Verify ONLY allowed keys are present (Black Box principle)
                $allowedKeys = [
                    'projectId', 'title', 'status', 'statusLabel', 'progressPercentage',
                    'deadline', 'budget', 'currency', 'uiApprovedAt', 'canApproveUI',
                    'createdAt', 'invoices',
                ];

                foreach ($allowedKeys as $key) {
                    $this->assertArrayHasKey($key, $response,
                        "ClientProjectResource should contain allowed key: {$key}");
                }

                // Verify FORBIDDEN keys are NOT present (Black Box security)
                $forbiddenKeys = [
                    'description', 'tasks', 'developers', 'wikis', 'timeEntries',
                    'internalNotes', 'client', 'quotation', 'histories', 'createdBy',
                    'updatedBy', 'clientId', 'quotationId', 'previousStatus',
                    'autoApprovalJobId', 'updatedAt',
                ];

                foreach ($forbiddenKeys as $key) {
                    $this->assertArrayNotHasKey($key, $response,
                        "ClientProjectResource should NOT contain forbidden key: {$key} (Black Box violation)");
                }

                // Verify data integrity for allowed fields
                $this->assertEquals($project->id, $response['projectId']);
                $this->assertEquals($project->title, $response['title']);
                $this->assertEquals($project->status->value, $response['status']);
                $this->assertEquals($project->status->label(), $response['statusLabel']);
                $this->assertEquals((float) $project->budget, $response['budget']);
                $this->assertEquals('USD', $response['currency']); // Default currency

                // Verify progress percentage is calculated (not exposed from internal tasks)
                $this->assertIsInt($response['progressPercentage']);
                $this->assertGreaterThanOrEqual(0, $response['progressPercentage']);
                $this->assertLessThanOrEqual(100, $response['progressPercentage']);

                // Verify canApproveUI logic
                $expectedCanApprove = ($project->status->value === 'ui_ux' && ! $project->ui_approved_at);
                $this->assertEquals($expectedCanApprove, $response['canApproveUI']);

                // Verify invoices are included using ClientInvoiceResource
                $this->assertArrayHasKey('invoices', $response);
                if (! empty($response['invoices'])) {
                    // Convert resource collection to array for testing
                    $invoicesArray = $response['invoices']->toArray(request());
                    $this->assertIsArray($invoicesArray);

                    foreach ($invoicesArray as $invoiceData) {
                        // Verify invoice data follows ClientInvoiceResource format
                        $this->assertArrayHasKey('invoiceId', $invoiceData);
                        $this->assertArrayHasKey('invoiceNumber', $invoiceData);
                        $this->assertArrayHasKey('amount', $invoiceData);
                        $this->assertArrayHasKey('status', $invoiceData);

                        // Verify invoice forbidden keys are not present
                        $this->assertArrayNotHasKey('notes', $invoiceData,
                            'Invoice in ClientProjectResource should not contain internal notes');
                        $this->assertArrayNotHasKey('createdBy', $invoiceData,
                            'Invoice in ClientProjectResource should not contain createdBy');
                        $this->assertArrayNotHasKey('updatedBy', $invoiceData,
                            'Invoice in ClientProjectResource should not contain updatedBy');
                    }
                }

                // Verify all keys are in camelCase format (API standard)
                foreach (array_keys($response) as $key) {
                    if (is_string($key)) {
                        $this->assertStringNotContainsString('_', $key,
                            "ClientProjectResource key '{$key}' should be camelCase, not snake_case");
                    }
                }
            });
    }

    /**
     * Property 3: ClientProjectResource Progress Calculation Black Box
     *
     * For any project, the progressPercentage SHALL be calculated without exposing
     * internal task details to the client. The calculation SHALL return 0 if no
     * tasks exist, and a value between 0-100 for projects with tasks.
     *
     * **Validates: Requirements 4.3**
     */
    public function test_client_project_resource_progress_calculation_black_box(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\names(), // title
                Generator\elements(['active', 'development', 'testing']) // status with potential tasks
            )
            ->then(function ($title, $status) {
                // Ensure we have valid data
                $title = $title ?: 'Test Project';

                // Create required models
                $client = User::factory()->create();
                $clientProfile = $this->ensureClientProfile($client);
                $creator = User::factory()->create();

                // Create project
                $project = Project::factory()->create([
                    'client_id' => $clientProfile->id,
                    'title' => $title,
                    'status' => $status,
                    'created_by' => $creator->id,
                ]);

                // Transform using ClientProjectResource
                $resource = new ClientProjectResource($project);
                $response = $resource->toArray(request());

                // Verify progress percentage is present and valid
                $this->assertArrayHasKey('progressPercentage', $response);
                $this->assertIsInt($response['progressPercentage']);

                // Verify progress is within valid range (0-100)
                $this->assertGreaterThanOrEqual(0, $response['progressPercentage'],
                    'Progress percentage should be >= 0');
                $this->assertLessThanOrEqual(100, $response['progressPercentage'],
                    'Progress percentage should be <= 100');

                // Verify no internal task data is exposed
                $this->assertArrayNotHasKey('tasks', $response,
                    'ClientProjectResource should not expose internal tasks');
                $this->assertArrayNotHasKey('taskCount', $response,
                    'ClientProjectResource should not expose task count');
                $this->assertArrayNotHasKey('completedTasks', $response,
                    'ClientProjectResource should not expose completed task count');

                // For projects without tasks relationship, progress should be 0
                // (This tests the CalculateProjectProgressAction edge case handling)
                if (! method_exists($project, 'tasks') || $project->tasks()->count() === 0) {
                    $this->assertEquals(0, $response['progressPercentage'],
                        'Projects without tasks should have 0% progress');
                }
            });
    }

    /**
     * Property 3: ClientProjectResource UI Approval Logic Black Box
     *
     * For any project, canApproveUI SHALL be true only when status is 'ui_ux'
     * and ui_approved_at is null. The logic SHALL not expose internal approval
     * workflow details.
     *
     * **Validates: Requirements 4.1, 4.2**
     */
    public function test_client_project_resource_ui_approval_logic_black_box(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\elements(['draft', 'active', 'ui_ux', 'development', 'testing', 'completed']), // status
                Generator\bool() // has ui_approved_at
            )
            ->then(function ($status, $hasUiApproval) {
                // Create required models
                $client = User::factory()->create();
                $clientProfile = $this->ensureClientProfile($client);
                $creator = User::factory()->create();

                // Create project with specific status and approval state
                $project = Project::factory()->create([
                    'client_id' => $clientProfile->id,
                    'status' => $status,
                    'ui_approved_at' => $hasUiApproval ? now() : null,
                    'created_by' => $creator->id,
                ]);

                // Transform using ClientProjectResource
                $resource = new ClientProjectResource($project);
                $response = $resource->toArray(request());

                // Verify canApproveUI logic
                $expectedCanApprove = ($status === 'ui_ux' && ! $hasUiApproval);
                $this->assertEquals($expectedCanApprove, $response['canApproveUI'],
                    'canApproveUI should be true only for ui_ux status without approval');

                // Verify no internal approval workflow data is exposed
                $this->assertArrayNotHasKey('autoApprovalJobId', $response,
                    'ClientProjectResource should not expose auto approval job ID');
                $this->assertArrayNotHasKey('previousStatus', $response,
                    'ClientProjectResource should not expose previous status');

                // Verify UI approval timestamp is included (but not internal details)
                $this->assertArrayHasKey('uiApprovedAt', $response);
                if ($hasUiApproval) {
                    $this->assertNotEmpty($response['uiApprovedAt']);
                } else {
                    $this->assertEmpty($response['uiApprovedAt']);
                }
            });
    }
}
