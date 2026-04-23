<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\V1\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Modules\Project\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Tests for API Response camelCase Format
 *
 * Feature: client-portal-security, Property 11: API Response camelCase Format
 * Validates: Requirements 3.7
 */
class InvoiceResourcePropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    /**
     * Property 11: API Response camelCase Format
     *
     * For any Invoice API response, all JSON keys SHALL be in camelCase format
     * (no snake_case keys in response body).
     *
     * **Validates: Requirements 3.7**
     */
    public function test_invoice_resource_camel_case_format(): void
    {
        $this
            ->limitTo(10)
            ->forAll(
                Generator\choose(100, 10000), // amount
                Generator\elements(['draft', 'sent', 'paid', 'overdue', 'cancelled']), // status
                Generator\elements(['USD', 'EUR', 'GBP']), // currency
                Generator\elements(['Test notes', 'Invoice notes', 'Payment notes', 'Service notes']), // notes
                Generator\bool() // has paid_at
            )
            ->then(function ($amount, $status, $currency, $notes, $hasPaidAt) {
                // Create required models
                $client = User::factory()->create();
                $clientProfile = $this->ensureClientProfile($client);
                $creator = User::factory()->create();
                $updater = User::factory()->create();

                $project = Project::factory()->create([
                    'client_id' => $clientProfile->id,
                    'created_by' => $creator->id,
                ]);

                // Create invoice with unique invoice number to avoid constraint violations
                $invoice = Invoice::factory()->create([
                    'project_id' => $project->id,
                    'invoice_number' => 'INV-TEST-'.uniqid(),
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => $status,
                    'notes' => $notes,
                    'paid_at' => $hasPaidAt ? Carbon::now() : null,
                    'created_by' => $creator->id,
                    'updated_by' => $updater->id,
                ]);

                // Transform using InvoiceResource
                $resource = new InvoiceResource($invoice);
                $response = $resource->toArray(request());

                // Verify all keys are in camelCase format
                $this->assertCamelCaseKeys($response, 'InvoiceResource');

                // Verify specific expected camelCase keys exist
                $expectedCamelCaseKeys = [
                    'invoiceId', 'projectId', 'invoiceNumber', 'amount', 'currency',
                    'status', 'statusLabel', 'dueDate', 'paidAt', 'notes',
                    'isOverdue', 'isPayable', 'createdBy', 'updatedBy',
                    'createdAt', 'updatedAt',
                ];

                foreach ($expectedCamelCaseKeys as $key) {
                    $this->assertArrayHasKey($key, $response,
                        "InvoiceResource should contain camelCase key: {$key}");
                }

                // Verify no snake_case keys exist
                $forbiddenSnakeCaseKeys = [
                    'invoice_id', 'project_id', 'invoice_number', 'due_date',
                    'paid_at', 'is_overdue', 'is_payable', 'created_by',
                    'updated_by', 'created_at', 'updated_at',
                ];

                foreach ($forbiddenSnakeCaseKeys as $key) {
                    $this->assertArrayNotHasKey($key, $response,
                        "InvoiceResource should NOT contain snake_case key: {$key}");
                }

                // Verify data integrity (values are preserved during camelCase conversion)
                $this->assertEquals($invoice->id, $response['invoiceId']);
                $this->assertEquals($invoice->project_id, $response['projectId']);
                $this->assertEquals($invoice->invoice_number, $response['invoiceNumber']);
                $this->assertEquals((float) $invoice->amount, $response['amount']);
                $this->assertEquals($invoice->currency, $response['currency']);
                $this->assertEquals($invoice->status->value, $response['status']);
                $this->assertEquals($invoice->status->label(), $response['statusLabel']);
                $this->assertEquals($invoice->notes, $response['notes']);
                $this->assertEquals($invoice->created_by, $response['createdBy']);
                $this->assertEquals($invoice->updated_by, $response['updatedBy']);

                // Verify boolean methods are preserved
                $this->assertEquals($invoice->isOverdue(), $response['isOverdue']);
                $this->assertEquals($invoice->isPayable(), $response['isPayable']);

                // Verify date formatting
                $this->assertEquals($invoice->due_date->toDateString(), $response['dueDate']);
                $this->assertEquals($invoice->created_at->toISOString(), $response['createdAt']);
                $this->assertEquals($invoice->updated_at->toISOString(), $response['updatedAt']);

                if ($invoice->paid_at) {
                    $this->assertEquals($invoice->paid_at->toISOString(), $response['paidAt']);
                } else {
                    $this->assertEmpty($response['paidAt']);
                }
            });
    }

    /**
     * Property 11: API Response camelCase Format - Nested Project Resource
     *
     * For any Invoice API response with loaded project relationship,
     * all nested keys SHALL also be in camelCase format.
     *
     * **Validates: Requirements 3.7**
     */
    public function test_invoice_resource_nested_project_camel_case(): void
    {
        $this
            ->limitTo(10)
            ->forAll(
                Generator\elements(['Project Alpha', 'Project Beta', 'Project Gamma', 'Test Project']), // project title
                Generator\choose(1000, 50000), // project budget
                Generator\elements(['active', 'development', 'testing', 'completed']) // project status
            )
            ->then(function ($projectTitle, $projectBudget, $projectStatus) {
                // Create required models
                $client = User::factory()->create();
                $clientProfile = $this->ensureClientProfile($client);
                $creator = User::factory()->create();

                $project = Project::factory()->create([
                    'client_id' => $clientProfile->id,
                    'title' => $projectTitle,
                    'budget' => $projectBudget,
                    'status' => $projectStatus,
                    'created_by' => $creator->id,
                ]);

                $invoice = Invoice::factory()->create([
                    'project_id' => $project->id,
                    'invoice_number' => 'INV-TEST-'.uniqid(),
                    'created_by' => $creator->id,
                ]);

                // Load the project relationship
                $invoice->load('project');

                // Transform using InvoiceResource (which includes nested ProjectResource)
                $resource = new InvoiceResource($invoice);
                $response = $resource->toArray(request());

                // Verify top-level keys are camelCase
                $this->assertCamelCaseKeys($response, 'InvoiceResource (with nested Project)');

                // Verify nested project resource keys are camelCase (if present)
                if (isset($response['project']) && is_array($response['project'])) {
                    $this->assertCamelCaseKeys($response['project'], 'Nested ProjectResource');

                    // Verify specific nested project keys
                    $expectedNestedKeys = [
                        'projectId', 'clientId', 'title', 'status', 'budget',
                        'deadline', 'createdAt', 'updatedAt',
                    ];

                    foreach ($expectedNestedKeys as $key) {
                        $this->assertArrayHasKey($key, $response['project'],
                            "Nested ProjectResource should contain camelCase key: {$key}");
                    }

                    // Verify no snake_case keys in nested resource
                    $forbiddenNestedKeys = [
                        'project_id', 'client_id', 'quotation_id', 'previous_status',
                        'ui_approved_at', 'auto_approval_job_id', 'created_by',
                        'updated_by', 'created_at', 'updated_at',
                    ];

                    foreach ($forbiddenNestedKeys as $key) {
                        $this->assertArrayNotHasKey($key, $response['project'],
                            "Nested ProjectResource should NOT contain snake_case key: {$key}");
                    }

                    // Verify nested data integrity
                    $this->assertEquals($project->id, $response['project']['projectId']);
                    $this->assertEquals($project->title, $response['project']['title']);
                    $this->assertEquals($project->status->value, $response['project']['status']);
                    $this->assertEquals((float) $project->budget, $response['project']['budget']);
                }
            });
    }

    /**
     * Property 11: API Response camelCase Format - All Field Types
     *
     * For any Invoice API response, all field types (strings, numbers, booleans,
     * dates, arrays) SHALL maintain camelCase key format regardless of their
     * database column naming.
     *
     * **Validates: Requirements 3.7**
     */
    public function test_invoice_resource_all_field_types_camel_case(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\choose(1, 999999), // invoice_number sequence
                Generator\choose(1, 365), // days until due
                Generator\bool(), // is overdue
                Generator\bool()  // is paid
            )
            ->then(function ($invoiceSequence, $daysUntilDue, $isOverdue, $isPaid) {
                // Create required models
                $client = User::factory()->create();
                $clientProfile = $this->ensureClientProfile($client);
                $creator = User::factory()->create();

                $project = Project::factory()->create([
                    'client_id' => $clientProfile->id,
                    'created_by' => $creator->id,
                ]);

                // Set up dates and status based on conditions
                $dueDate = $isOverdue ? Carbon::now()->subDays($daysUntilDue) : Carbon::now()->addDays($daysUntilDue);
                $status = $isPaid ? 'paid' : ($isOverdue ? 'overdue' : 'sent');
                $paidAt = $isPaid ? Carbon::now() : null;

                // Create invoice with specific invoice number format (unique to avoid conflicts)
                $invoiceNumber = sprintf('INV-TEST-%s-%06d', uniqid(), $invoiceSequence);

                $invoice = Invoice::factory()->create([
                    'project_id' => $project->id,
                    'invoice_number' => $invoiceNumber,
                    'status' => $status,
                    'due_date' => $dueDate,
                    'paid_at' => $paidAt,
                    'created_by' => $creator->id,
                ]);

                // Transform using InvoiceResource
                $resource = new InvoiceResource($invoice);
                $response = $resource->toArray(request());

                // Test different field types maintain camelCase

                // String fields (from snake_case columns)
                $this->assertArrayHasKey('invoiceNumber', $response);
                $this->assertIsString($response['invoiceNumber']);
                $this->assertEquals($invoiceNumber, $response['invoiceNumber']);

                // Integer fields (from snake_case columns)
                $this->assertArrayHasKey('projectId', $response);
                $this->assertIsInt($response['projectId']);
                $this->assertEquals($project->id, $response['projectId']);

                $this->assertArrayHasKey('createdBy', $response);
                $this->assertIsInt($response['createdBy']);
                $this->assertEquals($creator->id, $response['createdBy']);

                // Float fields
                $this->assertArrayHasKey('amount', $response);
                $this->assertIsFloat($response['amount']);

                // Boolean fields (computed methods)
                $this->assertArrayHasKey('isOverdue', $response);
                $this->assertIsBool($response['isOverdue']);

                $this->assertArrayHasKey('isPayable', $response);
                $this->assertIsBool($response['isPayable']);

                // Date fields (from snake_case columns)
                $this->assertArrayHasKey('dueDate', $response);
                $this->assertIsString($response['dueDate']);
                $this->assertEquals($dueDate->toDateString(), $response['dueDate']);

                $this->assertArrayHasKey('createdAt', $response);
                $this->assertIsString($response['createdAt']);

                $this->assertArrayHasKey('updatedAt', $response);
                $this->assertIsString($response['updatedAt']);

                // Nullable datetime field
                $this->assertArrayHasKey('paidAt', $response);
                if ($paidAt) {
                    $this->assertIsString($response['paidAt']);
                    $this->assertEquals($paidAt->toISOString(), $response['paidAt']);
                } else {
                    $this->assertEmpty($response['paidAt']);
                }

                // Verify no field uses snake_case regardless of type
                foreach (array_keys($response) as $key) {
                    if (is_string($key)) {
                        $this->assertStringNotContainsString('_', $key,
                            "All fields should be camelCase, found snake_case key: {$key}");
                    }
                }
            });
    }

    /**
     * Helper method to assert that all keys in an array are in camelCase format.
     *
     * @param  array  $array  The array to check
     * @param  string  $context  Context for error messages
     */
    private function assertCamelCaseKeys(array $array, string $context): void
    {
        foreach (array_keys($array) as $key) {
            // Skip non-string keys
            if (! is_string($key)) {
                continue;
            }

            // Check if key is in camelCase format
            $this->assertTrue(
                $this->isCamelCase($key),
                "Key '{$key}' in {$context} should be in camelCase format"
            );

            // Check if key contains underscores (snake_case indicator)
            $this->assertStringNotContainsString('_', $key,
                "Key '{$key}' in {$context} should not contain underscores (snake_case)");
        }
    }

    /**
     * Check if a string is in camelCase format.
     *
     * @param  string  $string  The string to check
     * @return bool True if the string is in camelCase format
     */
    private function isCamelCase(string $string): bool
    {
        // Empty strings are not camelCase
        if (empty($string)) {
            return false;
        }

        // Should start with lowercase letter
        if (! ctype_lower($string[0])) {
            return false;
        }

        // Should not contain underscores
        if (strpos($string, '_') !== false) {
            return false;
        }

        // Should not contain spaces
        if (strpos($string, ' ') !== false) {
            return false;
        }

        // Should only contain alphanumeric characters
        if (! ctype_alnum($string)) {
            return false;
        }

        return true;
    }
}
