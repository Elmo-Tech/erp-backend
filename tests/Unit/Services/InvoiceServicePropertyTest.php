<?php

namespace Tests\Unit\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\Client\AuditLogService;
use App\Services\Client\InvoiceService;
use Carbon\Carbon;
use Eris\Generator;
use Eris\TestTrait;
use Tests\ErisDatabaseTestCase;

/**
 * Property-Based Tests for InvoiceService
 *
 * Feature: client-portal-security, Property 12: Overdue Invoice Processing
 * Validates: Requirements 3.8, 18.2, 18.3
 */
class InvoiceServicePropertyTest extends ErisDatabaseTestCase
{
    use TestTrait;

    private InvoiceService $invoiceService;

    private AuditLogService $auditLogService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditLogService = new AuditLogService;
        $this->invoiceService = new InvoiceService($this->auditLogService);
    }

    protected function tearDown(): void
    {
        // Clean up all invoices to prevent test interference
        Invoice::query()->delete();

        parent::tearDown();
    }

    /**
     * Property 12: Overdue Invoice Processing
     *
     * For any invoice with due_date in the past AND status 'sent', the system SHALL
     * update status to 'overdue'. The system SHALL NOT change status of invoices
     * that are already 'paid', 'cancelled', or 'draft'.
     *
     * **Validates: Requirements 3.8, 18.2, 18.3**
     */
    public function test_overdue_invoice_processing_updates_sent_invoices(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1, 10), // number of overdue invoices to create
                Generator\choose(1, 30)  // days past due (1-30 days)
            )
            ->then(function ($numInvoices, $daysPastDue) {
                // Clean up any existing invoices to ensure clean test
                Invoice::query()->delete();

                $project = Project::factory()->create();
                $overdueInvoices = [];

                // Create invoices with 'sent' status that are past due
                for ($i = 0; $i < $numInvoices; $i++) {
                    $invoice = Invoice::factory()->create([
                        'project_id' => $project->id,
                        'status' => InvoiceStatus::SENT,
                        'due_date' => Carbon::today()->subDays($daysPastDue + $i),
                    ]);
                    $overdueInvoices[] = $invoice;
                }

                // Process overdue invoices
                $count = $this->invoiceService->markOverdueInvoices();

                // Verify correct number of invoices were updated
                $this->assertEquals($numInvoices, $count,
                    'Should update all sent invoices that are past due');

                // Verify each invoice status was updated to overdue
                foreach ($overdueInvoices as $invoice) {
                    $invoice->refresh();
                    $this->assertEquals(InvoiceStatus::OVERDUE, $invoice->status,
                        'Sent invoice past due date should be marked as overdue');
                }

                // Verify database state
                $this->assertEquals($numInvoices,
                    Invoice::where('status', InvoiceStatus::OVERDUE)->count(),
                    'Database should contain correct number of overdue invoices');
            });
    }

    /**
     * Property 12: Overdue Invoice Processing - Preserves Non-Sent Statuses
     *
     * For any invoice with status other than 'sent', the overdue processing
     * SHALL NOT change the status regardless of due date.
     *
     * **Validates: Requirements 18.3**
     */
    public function test_overdue_invoice_processing_preserves_non_sent_statuses(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\elements([InvoiceStatus::DRAFT, InvoiceStatus::PAID, InvoiceStatus::CANCELLED]), // non-sent statuses
                Generator\choose(1, 5),  // number of invoices per status
                Generator\choose(1, 60)  // days past due
            )
            ->then(function ($status, $numInvoices, $daysPastDue) {
                $project = Project::factory()->create();
                $invoices = [];

                // Create invoices with non-sent status that are past due
                for ($i = 0; $i < $numInvoices; $i++) {
                    $invoice = Invoice::factory()->create([
                        'project_id' => $project->id,
                        'status' => $status,
                        'due_date' => Carbon::today()->subDays($daysPastDue + $i),
                    ]);
                    $invoices[] = $invoice;
                }

                // Process overdue invoices
                $count = $this->invoiceService->markOverdueInvoices();

                // Should not update any non-sent invoices
                $this->assertEquals(0, $count,
                    'Should not update invoices with non-sent status');

                // Verify each invoice status remains unchanged
                foreach ($invoices as $invoice) {
                    $invoice->refresh();
                    $this->assertEquals($status, $invoice->status,
                        "Invoice with {$status->value} status should remain unchanged");
                }

                // Verify no overdue invoices were created
                $this->assertEquals(0,
                    Invoice::where('status', InvoiceStatus::OVERDUE)->count(),
                    'No invoices should be marked overdue when they have non-sent status');
            });
    }

    /**
     * Property 12: Overdue Invoice Processing - Future Due Dates Preserved
     *
     * For any invoice with due_date in the future, the overdue processing
     * SHALL NOT change the status regardless of current status.
     *
     * **Validates: Requirements 18.2**
     */
    public function test_overdue_invoice_processing_preserves_future_due_dates(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\elements([InvoiceStatus::DRAFT, InvoiceStatus::SENT]), // statuses that could be updated
                Generator\choose(1, 5),  // number of invoices
                Generator\choose(1, 30)  // days in future
            )
            ->then(function ($status, $numInvoices, $daysInFuture) {
                $project = Project::factory()->create();
                $invoices = [];

                // Create invoices with future due dates
                for ($i = 0; $i < $numInvoices; $i++) {
                    $invoice = Invoice::factory()->create([
                        'project_id' => $project->id,
                        'status' => $status,
                        'due_date' => Carbon::today()->addDays($daysInFuture + $i),
                    ]);
                    $invoices[] = $invoice;
                }

                // Process overdue invoices
                $count = $this->invoiceService->markOverdueInvoices();

                // Should not update any invoices with future due dates
                $this->assertEquals(0, $count,
                    'Should not update invoices with future due dates');

                // Verify each invoice status remains unchanged
                foreach ($invoices as $invoice) {
                    $invoice->refresh();
                    $this->assertEquals($status, $invoice->status,
                        "Invoice with future due date should maintain {$status->value} status");
                }
            });
    }

    /**
     * Property 12: Overdue Invoice Processing - Mixed Scenarios
     *
     * For any combination of invoices with different statuses and due dates,
     * only 'sent' invoices past due SHALL be updated to 'overdue'.
     *
     * **Validates: Requirements 3.8, 18.2, 18.3**
     */
    public function test_overdue_invoice_processing_mixed_scenarios(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1, 3), // sent overdue invoices
                Generator\choose(1, 3), // sent future invoices
                Generator\choose(1, 3), // paid overdue invoices
                Generator\choose(1, 3)  // draft overdue invoices
            )
            ->then(function ($sentOverdue, $sentFuture, $paidOverdue, $draftOverdue) {
                // Clean up any existing invoices to ensure clean test
                Invoice::query()->delete();

                $project = Project::factory()->create();

                // Create sent invoices that are overdue (should be updated)
                $sentOverdueInvoices = [];
                for ($i = 0; $i < $sentOverdue; $i++) {
                    $sentOverdueInvoices[] = Invoice::factory()->create([
                        'project_id' => $project->id,
                        'status' => InvoiceStatus::SENT,
                        'due_date' => Carbon::today()->subDays($i + 1),
                    ]);
                }

                // Create sent invoices with future due dates (should not be updated)
                $sentFutureInvoices = [];
                for ($i = 0; $i < $sentFuture; $i++) {
                    $sentFutureInvoices[] = Invoice::factory()->create([
                        'project_id' => $project->id,
                        'status' => InvoiceStatus::SENT,
                        'due_date' => Carbon::today()->addDays($i + 1),
                    ]);
                }

                // Create paid invoices that are overdue (should not be updated)
                $paidOverdueInvoices = [];
                for ($i = 0; $i < $paidOverdue; $i++) {
                    $paidOverdueInvoices[] = Invoice::factory()->create([
                        'project_id' => $project->id,
                        'status' => InvoiceStatus::PAID,
                        'due_date' => Carbon::today()->subDays($i + 1),
                    ]);
                }

                // Create draft invoices that are overdue (should not be updated)
                $draftOverdueInvoices = [];
                for ($i = 0; $i < $draftOverdue; $i++) {
                    $draftOverdueInvoices[] = Invoice::factory()->create([
                        'project_id' => $project->id,
                        'status' => InvoiceStatus::DRAFT,
                        'due_date' => Carbon::today()->subDays($i + 1),
                    ]);
                }

                // Process overdue invoices
                $count = $this->invoiceService->markOverdueInvoices();

                // Should only update sent overdue invoices
                $this->assertEquals($sentOverdue, $count,
                    'Should only update sent invoices that are past due');

                // Verify sent overdue invoices were updated
                foreach ($sentOverdueInvoices as $invoice) {
                    $invoice->refresh();
                    $this->assertEquals(InvoiceStatus::OVERDUE, $invoice->status,
                        'Sent overdue invoice should be marked as overdue');
                }

                // Verify sent future invoices were not updated
                foreach ($sentFutureInvoices as $invoice) {
                    $invoice->refresh();
                    $this->assertEquals(InvoiceStatus::SENT, $invoice->status,
                        'Sent invoice with future due date should remain sent');
                }

                // Verify paid overdue invoices were not updated
                foreach ($paidOverdueInvoices as $invoice) {
                    $invoice->refresh();
                    $this->assertEquals(InvoiceStatus::PAID, $invoice->status,
                        'Paid invoice should remain paid regardless of due date');
                }

                // Verify draft overdue invoices were not updated
                foreach ($draftOverdueInvoices as $invoice) {
                    $invoice->refresh();
                    $this->assertEquals(InvoiceStatus::DRAFT, $invoice->status,
                        'Draft invoice should remain draft regardless of due date');
                }

                // Verify total overdue count
                $this->assertEquals($sentOverdue,
                    Invoice::where('status', InvoiceStatus::OVERDUE)->count(),
                    'Total overdue invoices should match sent overdue count');
            });
    }

    /**
     * Property 12: Overdue Invoice Processing - Today's Due Date
     *
     * For any invoice with due_date equal to today, the overdue processing
     * SHALL NOT mark it as overdue (only past due dates).
     *
     * **Validates: Requirements 18.2**
     */
    public function test_overdue_invoice_processing_today_due_date(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1, 5) // number of invoices due today
            )
            ->then(function ($numInvoices) {
                $project = Project::factory()->create();
                $invoices = [];

                // Create invoices due today
                for ($i = 0; $i < $numInvoices; $i++) {
                    $invoices[] = Invoice::factory()->create([
                        'project_id' => $project->id,
                        'status' => InvoiceStatus::SENT,
                        'due_date' => Carbon::today(), // Due today, not overdue yet
                    ]);
                }

                // Process overdue invoices
                $count = $this->invoiceService->markOverdueInvoices();

                // Should not update invoices due today
                $this->assertEquals(0, $count,
                    'Should not mark invoices due today as overdue');

                // Verify each invoice status remains sent
                foreach ($invoices as $invoice) {
                    $invoice->refresh();
                    $this->assertEquals(InvoiceStatus::SENT, $invoice->status,
                        'Invoice due today should remain sent, not overdue');
                }

                // Verify no overdue invoices exist
                $this->assertEquals(0,
                    Invoice::where('status', InvoiceStatus::OVERDUE)->count(),
                    'No invoices should be marked overdue when due today');
            });
    }

    /**
     * Property 12: Overdue Invoice Processing - Empty Database
     *
     * For any empty database or database with no sent invoices,
     * the overdue processing SHALL return 0 and not cause errors.
     *
     * **Validates: Requirements 18.2, 18.3**
     */
    public function test_overdue_invoice_processing_empty_database(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(0, 1) // 0 = empty DB, 1 = DB with non-sent invoices
            )
            ->then(function ($scenario) {
                if ($scenario === 1) {
                    // Create some non-sent invoices
                    $project = Project::factory()->create();
                    Invoice::factory()->create([
                        'project_id' => $project->id,
                        'status' => InvoiceStatus::PAID,
                        'due_date' => Carbon::today()->subDays(10),
                    ]);
                    Invoice::factory()->create([
                        'project_id' => $project->id,
                        'status' => InvoiceStatus::DRAFT,
                        'due_date' => Carbon::today()->subDays(5),
                    ]);
                }

                // Process overdue invoices
                $count = $this->invoiceService->markOverdueInvoices();

                // Should return 0 for empty database or no sent invoices
                $this->assertEquals(0, $count,
                    'Should return 0 when no sent overdue invoices exist');

                // Should not cause any errors or exceptions
                $this->assertIsInt($count);
                $this->assertGreaterThanOrEqual(0, $count);
            });
    }

    /**
     * Property 12: Overdue Invoice Processing - Already Overdue Invoices
     *
     * For any invoice already marked as 'overdue', the processing SHALL
     * not count it again or modify it further.
     *
     * **Validates: Requirements 18.3**
     */
    public function test_overdue_invoice_processing_already_overdue(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1, 5), // number of already overdue invoices
                Generator\choose(1, 5)  // number of newly overdue invoices
            )
            ->then(function ($alreadyOverdue, $newlyOverdue) {
                // Clean up any existing invoices to ensure clean test
                Invoice::query()->delete();

                $project = Project::factory()->create();

                // Create invoices already marked as overdue
                $existingOverdueInvoices = [];
                for ($i = 0; $i < $alreadyOverdue; $i++) {
                    $existingOverdueInvoices[] = Invoice::factory()->create([
                        'project_id' => $project->id,
                        'status' => InvoiceStatus::OVERDUE,
                        'due_date' => Carbon::today()->subDays($i + 10),
                    ]);
                }

                // Create sent invoices that should become overdue
                $newOverdueInvoices = [];
                for ($i = 0; $i < $newlyOverdue; $i++) {
                    $newOverdueInvoices[] = Invoice::factory()->create([
                        'project_id' => $project->id,
                        'status' => InvoiceStatus::SENT,
                        'due_date' => Carbon::today()->subDays($i + 1),
                    ]);
                }

                // Process overdue invoices
                $count = $this->invoiceService->markOverdueInvoices();

                // Should only count newly updated invoices
                $this->assertEquals($newlyOverdue, $count,
                    'Should only count newly updated invoices, not already overdue ones');

                // Verify existing overdue invoices remain unchanged
                foreach ($existingOverdueInvoices as $invoice) {
                    $invoice->refresh();
                    $this->assertEquals(InvoiceStatus::OVERDUE, $invoice->status,
                        'Already overdue invoice should remain overdue');
                }

                // Verify newly overdue invoices were updated
                foreach ($newOverdueInvoices as $invoice) {
                    $invoice->refresh();
                    $this->assertEquals(InvoiceStatus::OVERDUE, $invoice->status,
                        'Newly overdue invoice should be marked as overdue');
                }

                // Verify total overdue count
                $this->assertEquals($alreadyOverdue + $newlyOverdue,
                    Invoice::where('status', InvoiceStatus::OVERDUE)->count(),
                    'Total overdue count should include both existing and new');
            });
    }
}
