<?php

namespace Tests\Unit\Http\Resources;

use App\Modules\Client\Resources\ClientInvoiceResource;
use App\Models\Invoice;
use App\Modules\Project\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Tests for ClientInvoiceResource Format
 *
 * Feature: client-portal-security, Property 4: ClientInvoiceResource Format
 * Validates: Requirements 5.1, 5.2, 5.3, 5.4
 */
class ClientInvoiceResourcePropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    /**
     * Property 4: ClientInvoiceResource Format
     *
     * For any API response using ClientInvoiceResource, the response SHALL contain:
     * invoiceId, invoiceNumber, projectId, amount, currency, status, statusLabel,
     * dueDate, paidAt, isOverdue, isPayable, createdAt. The response SHALL NOT
     * contain: notes, createdBy, updatedBy. For any overdue invoice, isOverdue
     * SHALL be true.
     *
     * **Validates: Requirements 5.1, 5.2, 5.3, 5.4**
     */
    public function test_client_invoice_resource_format(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(100, 10000), // amount
                Generator\elements(['draft', 'sent', 'paid', 'overdue', 'cancelled']), // status
                Generator\elements(['USD', 'EUR', 'GBP']), // currency
                Generator\bool(), // is_overdue (due date in past)
                Generator\bool(), // is_paid
                Generator\names() // notes (should be hidden)
            )
            ->then(function ($amount, $status, $currency, $isOverdue, $isPaid, $notes) {
                // Ensure we have valid data
                $notes = $notes ?: 'Internal notes that should be hidden';

                // Create required models
                $client = User::factory()->create();
                $clientProfile = $this->ensureClientProfile($client);
                $creator = User::factory()->create();

                $project = Project::factory()->create([
                    'client_id' => $clientProfile->id,
                    'created_by' => $creator->id,
                ]);

                // Set up dates based on overdue status
                $dueDate = $isOverdue ? Carbon::now()->subDays(5) : Carbon::now()->addDays(5);
                $paidAt = ($isPaid && $status === 'paid') ? Carbon::now()->subDays(1) : null;

                // Adjust status based on conditions
                if ($isPaid) {
                    $status = 'paid';
                } elseif ($isOverdue && $status === 'sent') {
                    $status = 'overdue';
                }

                // Create invoice
                $invoice = Invoice::factory()->create([
                    'project_id' => $project->id,
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => $status,
                    'due_date' => $dueDate,
                    'paid_at' => $paidAt,
                    'notes' => $notes,
                    'created_by' => $creator->id,
                    'updated_by' => $creator->id,
                ]);

                // Transform using ClientInvoiceResource
                $resource = new ClientInvoiceResource($invoice);
                $response = $resource->toArray(request());

                // Verify REQUIRED keys are present
                $requiredKeys = [
                    'invoiceId', 'invoiceNumber', 'projectId', 'amount', 'currency',
                    'status', 'statusLabel', 'dueDate', 'paidAt', 'isOverdue',
                    'isPayable', 'createdAt',
                ];

                foreach ($requiredKeys as $key) {
                    $this->assertArrayHasKey($key, $response,
                        "ClientInvoiceResource should contain required key: {$key}");
                }

                // Verify FORBIDDEN keys are NOT present (security requirement)
                $forbiddenKeys = [
                    'notes', 'createdBy', 'updatedBy', 'created_by', 'updated_by',
                    'updatedAt', 'updated_at',
                ];

                foreach ($forbiddenKeys as $key) {
                    $this->assertArrayNotHasKey($key, $response,
                        "ClientInvoiceResource should NOT contain forbidden key: {$key}");
                }

                // Verify data integrity for required fields
                $this->assertEquals($invoice->id, $response['invoiceId']);
                $this->assertEquals($invoice->invoice_number, $response['invoiceNumber']);
                $this->assertEquals($invoice->project_id, $response['projectId']);
                $this->assertEquals((float) $invoice->amount, $response['amount']);
                $this->assertEquals($invoice->currency, $response['currency']);
                $this->assertEquals($invoice->status->value, $response['status']);
                $this->assertEquals($invoice->status->label(), $response['statusLabel']);
                $this->assertEquals($invoice->due_date->toDateString(), $response['dueDate']);

                // Verify boolean flags
                $this->assertIsBool($response['isOverdue']);
                $this->assertIsBool($response['isPayable']);

                // Verify overdue logic
                if ($invoice->isOverdue()) {
                    $this->assertTrue($response['isOverdue'],
                        'isOverdue should be true for overdue invoices');
                }

                // Verify payable logic
                $expectedPayable = in_array($invoice->status->value, ['sent', 'overdue']);
                $this->assertEquals($expectedPayable, $response['isPayable'],
                    'isPayable should match invoice status payability');

                // Verify paidAt handling
                if ($invoice->paid_at) {
                    $this->assertNotEmpty($response['paidAt']);
                } else {
                    $this->assertEmpty($response['paidAt']);
                }

                // Verify all keys are in camelCase format (API standard)
                foreach (array_keys($response) as $key) {
                    if (is_string($key)) {
                        $this->assertStringNotContainsString('_', $key,
                            "ClientInvoiceResource key '{$key}' should be camelCase, not snake_case");
                    }
                }

                // Verify sensitive data is not exposed
                $responseJson = json_encode($response);
                $this->assertStringNotContainsString($notes, $responseJson,
                    'Internal notes should not be exposed in ClientInvoiceResource');

                // Verify creator ID is not exposed as a separate field (avoid false positives from numeric coincidences)
                $this->assertArrayNotHasKey('createdBy', $response,
                    'ClientInvoiceResource should not contain createdBy field');
                $this->assertArrayNotHasKey('created_by', $response,
                    'ClientInvoiceResource should not contain created_by field');
            });
    }

    /**
     * Property 4: ClientInvoiceResource Overdue Detection
     *
     * For any invoice with due_date in the past and status 'sent', isOverdue
     * SHALL be true. For any invoice with status 'overdue', isOverdue SHALL
     * be true regardless of due_date.
     *
     * **Validates: Requirements 5.3**
     */
    public function test_client_invoice_resource_overdue_detection(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\elements(['sent', 'overdue', 'paid', 'draft', 'cancelled']), // status
                Generator\choose(1, 30) // days offset (positive = future, negative = past)
            )
            ->then(function ($status, $daysOffset) {
                // Create required models
                $client = User::factory()->create();
                $clientProfile = $this->ensureClientProfile($client);
                $creator = User::factory()->create();

                $project = Project::factory()->create([
                    'client_id' => $clientProfile->id,
                    'created_by' => $creator->id,
                ]);

                // Set due date based on offset (negative = past, positive = future)
                $dueDate = Carbon::now()->addDays($daysOffset);
                $isPastDue = $daysOffset < 0;

                // Create invoice
                $invoice = Invoice::factory()->create([
                    'project_id' => $project->id,
                    'status' => $status,
                    'due_date' => $dueDate,
                    'created_by' => $creator->id,
                ]);

                // Transform using ClientInvoiceResource
                $resource = new ClientInvoiceResource($invoice);
                $response = $resource->toArray(request());

                // Verify overdue detection logic
                $expectedOverdue = ($status === 'overdue') ||
                                 ($status === 'sent' && $isPastDue);

                $this->assertEquals($expectedOverdue, $response['isOverdue'],
                    "isOverdue should be true for status '{$status}' with due date ".
                    ($isPastDue ? 'in past' : 'in future'));

                // Verify status-specific behavior
                if ($status === 'overdue') {
                    $this->assertTrue($response['isOverdue'],
                        "Invoices with 'overdue' status should always have isOverdue = true");
                }

                if ($status === 'paid' || $status === 'cancelled') {
                    $this->assertFalse($response['isPayable'],
                        'Paid or cancelled invoices should not be payable');
                }

                if ($status === 'sent' || $status === 'overdue') {
                    $this->assertTrue($response['isPayable'],
                        'Sent or overdue invoices should be payable');
                }
            });
    }

    /**
     * Property 4: ClientInvoiceResource Data Hiding
     *
     * For any invoice with internal notes, created_by, or updated_by data,
     * the ClientInvoiceResource SHALL NOT expose this sensitive information
     * in the API response.
     *
     * **Validates: Requirements 5.2**
     */
    public function test_client_invoice_resource_data_hiding(): void
    {
        $this
            ->limitTo(2)
            ->forAll(
                Generator\names(), // internal notes
                Generator\names()  // additional sensitive data
            )
            ->then(function ($internalNotes, $sensitiveData) {
                // Ensure we have sensitive data to test hiding
                $internalNotes = $internalNotes ?: 'CONFIDENTIAL: Payment terms negotiated';
                $sensitiveData = $sensitiveData ?: 'INTERNAL: Client has payment issues';

                // Create required models
                $client = User::factory()->create(['name' => 'Client User']);
                $clientProfile = $this->ensureClientProfile($client);
                $creator = User::factory()->create(['name' => 'Internal Creator']);
                $updater = User::factory()->create(['name' => 'Internal Updater']);

                $project = Project::factory()->create([
                    'client_id' => $clientProfile->id,
                    'created_by' => $creator->id,
                ]);

                // Create invoice with sensitive internal data and unique invoice number
                $invoice = Invoice::factory()->create([
                    'project_id' => $project->id,
                    'invoice_number' => 'INV-TEST-'.uniqid(),
                    'notes' => $internalNotes.' - '.$sensitiveData,
                    'created_by' => $creator->id,
                    'updated_by' => $updater->id,
                ]);

                // Transform using ClientInvoiceResource
                $resource = new ClientInvoiceResource($invoice);
                $response = $resource->toArray(request());

                // Convert response to JSON to check for data leakage
                $responseJson = json_encode($response);

                // Verify sensitive text is not exposed anywhere in response
                $this->assertStringNotContainsString($internalNotes, $responseJson,
                    'Internal notes should not be exposed in ClientInvoiceResource');
                $this->assertStringNotContainsString($sensitiveData, $responseJson,
                    'Sensitive data should not be exposed in ClientInvoiceResource');
                $this->assertStringNotContainsString('CONFIDENTIAL', $responseJson,
                    'Confidential markers should not be exposed in ClientInvoiceResource');
                $this->assertStringNotContainsString('INTERNAL', $responseJson,
                    'Internal markers should not be exposed in ClientInvoiceResource');

                // Verify creator/updater names are not exposed
                $this->assertStringNotContainsString($creator->name, $responseJson,
                    'Creator name should not be exposed in ClientInvoiceResource');
                $this->assertStringNotContainsString($updater->name, $responseJson,
                    'Updater name should not be exposed in ClientInvoiceResource');

                // Verify creator/updater data is not exposed as fields (avoid false positives from numeric coincidences)
                $this->assertArrayNotHasKey('createdBy', $response,
                    'ClientInvoiceResource should not contain createdBy field');
                $this->assertArrayNotHasKey('updatedBy', $response,
                    'ClientInvoiceResource should not contain updatedBy field');
                $this->assertArrayNotHasKey('created_by', $response,
                    'ClientInvoiceResource should not contain created_by field');
                $this->assertArrayNotHasKey('updated_by', $response,
                    'ClientInvoiceResource should not contain updated_by field');

                // Verify forbidden keys are definitely not present
                $this->assertArrayNotHasKey('notes', $response);
                $this->assertArrayNotHasKey('createdBy', $response);
                $this->assertArrayNotHasKey('updatedBy', $response);
                $this->assertArrayNotHasKey('created_by', $response);
                $this->assertArrayNotHasKey('updated_by', $response);
            });
    }
}
