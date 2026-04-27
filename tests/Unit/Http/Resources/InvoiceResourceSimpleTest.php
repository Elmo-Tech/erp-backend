<?php

namespace Tests\Unit\Http\Resources;

use App\Modules\Invoice\Resources\Invoices\InvoiceResource;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Project\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Simple Unit Tests for InvoiceResource
 *
 * Validates: Requirements 3.7, 10.2
 */
class InvoiceResourceSimpleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that InvoiceResource returns all required fields in camelCase format.
     */
    public function test_invoice_resource_returns_camel_case_fields(): void
    {
        // Create required models
        $client = User::factory()->create();
        $clientProfile = $this->ensureClientProfile($client);
        $creator = User::factory()->create();

        $project = Project::factory()->create([
            'client_id' => $clientProfile->id,
            'created_by' => $creator->id,
        ]);

        $invoice = Invoice::factory()->create([
            'project_id' => $project->id,
            'invoice_number' => 'INV-TEST-001',
            'amount' => 1500.50,
            'currency' => 'USD',
            'status' => 'sent',
            'notes' => 'Test invoice notes',
            'paid_at' => null,
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);

        // Transform using InvoiceResource
        $resource = new InvoiceResource($invoice);
        $response = $resource->toArray(request());

        // Verify all expected camelCase keys exist
        $expectedKeys = [
            'invoiceId', 'projectId', 'invoiceNumber', 'amount', 'currency',
            'status', 'statusLabel', 'dueDate', 'paidAt', 'notes',
            'isOverdue', 'isPayable', 'createdBy', 'updatedBy',
            'createdAt', 'updatedAt',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $response,
                "InvoiceResource should contain camelCase key: {$key}");
        }

        // Verify no snake_case keys exist
        $forbiddenKeys = [
            'invoice_id', 'project_id', 'invoice_number', 'due_date',
            'paid_at', 'is_overdue', 'is_payable', 'created_by',
            'updated_by', 'created_at', 'updated_at',
        ];

        foreach ($forbiddenKeys as $key) {
            $this->assertArrayNotHasKey($key, $response,
                "InvoiceResource should NOT contain snake_case key: {$key}");
        }

        // Verify data integrity
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

        // Verify boolean methods
        $this->assertEquals($invoice->isOverdue(), $response['isOverdue']);
        $this->assertEquals($invoice->isPayable(), $response['isPayable']);

        // Verify date formatting
        $this->assertEquals($invoice->due_date->toDateString(), $response['dueDate']);
        $this->assertEquals($invoice->created_at->toISOString(), $response['createdAt']);
        $this->assertEquals($invoice->updated_at->toISOString(), $response['updatedAt']);
        $this->assertEmpty($response['paidAt']); // Should be empty since paid_at is null
    }

    /**
     * Test that InvoiceResource handles paid invoices correctly.
     */
    public function test_invoice_resource_handles_paid_invoice(): void
    {
        // Create required models
        $client = User::factory()->create();
        $clientProfile = $this->ensureClientProfile($client);
        $creator = User::factory()->create();

        $project = Project::factory()->create([
            'client_id' => $clientProfile->id,
            'created_by' => $creator->id,
        ]);

        $paidAt = Carbon::now();

        $invoice = Invoice::factory()->create([
            'project_id' => $project->id,
            'status' => 'paid',
            'paid_at' => $paidAt,
            'created_by' => $creator->id,
        ]);

        // Transform using InvoiceResource
        $resource = new InvoiceResource($invoice);
        $response = $resource->toArray(request());

        // Verify paid_at is properly formatted (allow for microsecond differences)
        $this->assertStringStartsWith($paidAt->utc()->format('Y-m-d\TH:i:s'), $response['paidAt']);
        $this->assertEquals('paid', $response['status']);
        $this->assertFalse($response['isPayable']); // Paid invoices are not payable
    }
}
