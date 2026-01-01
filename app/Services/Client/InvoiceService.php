<?php

namespace App\Services\Client;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Project;
use Carbon\Carbon;
use InvalidArgumentException;

class InvoiceService
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    /**
     * Create a new invoice for a project.
     * 
     * Requirements: 3.3, 10.3
     */
    public function createInvoice(Project $project, array $data): Invoice
    {
        $invoice = Invoice::create([
            'project_id' => $project->id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'USD',
            'status' => InvoiceStatus::DRAFT,
            'due_date' => $data['dueDate'],
            'notes' => $data['notes'] ?? null,
        ]);

        $this->auditLogService->log('invoice_created', $invoice);

        return $invoice;
    }

    /**
     * Mark an invoice as paid.
     * 
     * Requirements: 8.3
     */
    public function markAsPaid(Invoice $invoice): Invoice
    {
        if (!$invoice->isPayable()) {
            throw new InvalidArgumentException('Invoice cannot be paid in current status.');
        }

        $invoice->update([
            'status' => InvoiceStatus::PAID,
            'paid_at' => Carbon::now(),
        ]);

        $this->auditLogService->log('invoice_paid', $invoice);

        return $invoice->fresh();
    }

    /**
     * Mark overdue invoices (called by scheduled job).
     * 
     * Requirements: 3.8, 18.2, 18.3
     */
    public function markOverdueInvoices(): int
    {
        $count = Invoice::withoutGlobalScopes()
            ->where('status', InvoiceStatus::SENT)
            ->where('due_date', '<', Carbon::today())
            ->update(['status' => InvoiceStatus::OVERDUE]);

        return $count;
    }
}