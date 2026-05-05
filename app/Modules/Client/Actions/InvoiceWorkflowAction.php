<?php

namespace App\Modules\Client\Actions;

use App\Enums\InvoiceStatus;
use App\Enums\MilestoneStatus;
use App\Modules\Contract\Models\ContractMilestone;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Project\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvoiceWorkflowAction
{
    public function __construct(
        private LogClientAuditAction $auditLogService
    ) {}

    /**
     * Create a new invoice for a project.
     *
     * Requirements: 3.3, 10.3
     */
    public function createInvoice(Project $project, array $data): Invoice
    {
        $invoice = Invoice::create([
            'model_type' => Project::class,
            'model_id' => $project->id,
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
     * Create invoice from contract milestone.
     * Links the invoice to the milestone and updates milestone status.
     */
    public function createFromMilestone(ContractMilestone $milestone, array $data = []): Invoice
    {
        // Validate milestone can have invoice created
        if ($milestone->invoice_id) {
            throw new InvalidArgumentException('Milestone already has an invoice linked.');
        }

        if ($milestone->status === MilestoneStatus::PAID) {
            throw new InvalidArgumentException('Cannot create invoice for paid milestone.');
        }

        // Get project from contract
        $contract = $milestone->contract;
        if (! $contract->project_id) {
            throw new InvalidArgumentException('Contract must be linked to a project to create invoice.');
        }

        return DB::transaction(function () use ($milestone, $contract, $data) {
            // Create the invoice
            $invoice = Invoice::create([
                'model_type' => Project::class,
                'model_id' => $contract->project_id,
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'amount' => $milestone->amount,
                'currency' => $contract->currency,
                'status' => InvoiceStatus::DRAFT,
                'due_date' => $data['dueDate'] ?? $milestone->due_date ?? now()->addDays(14),
                'notes' => $data['notes'] ?? "Payment for milestone: {$milestone->title}",
            ]);

            // Link invoice to milestone and update status
            $milestone->update([
                'invoice_id' => $invoice->id,
                'status' => MilestoneStatus::INVOICED,
            ]);

            $this->auditLogService->log('invoice_created_from_milestone', $invoice, [
                'milestone_id' => $milestone->id,
                'milestone_title' => $milestone->title,
                'contract_id' => $contract->id,
            ]);

            return $invoice->load('project');
        });
    }

    /**
     * Mark an invoice as paid.
     * If linked to a milestone, updates milestone status too.
     *
     * Requirements: 8.3
     */
    public function markAsPaid(Invoice $invoice): Invoice
    {
        if (! $invoice->isPayable()) {
            throw new InvalidArgumentException('Invoice cannot be paid in current status.');
        }

        return DB::transaction(function () use ($invoice) {
            $invoice->update([
                'status' => InvoiceStatus::PAID,
                'paid_at' => Carbon::now(),
            ]);

            // Update linked milestone if exists
            $milestone = ContractMilestone::where('invoice_id', $invoice->id)->first();
            if ($milestone) {
                $milestone->update([
                    'status' => MilestoneStatus::PAID,
                    'paid_at' => Carbon::now(),
                ]);

                $this->auditLogService->log('milestone_paid_via_invoice', $invoice, [
                    'milestone_id' => $milestone->id,
                    'milestone_title' => $milestone->title,
                ]);
            }

            $this->auditLogService->log('invoice_paid', $invoice);

            return $invoice->fresh();
        });
    }

    /**
     * Send invoice to client.
     */
    public function sendInvoice(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            throw new InvalidArgumentException('Only draft invoices can be sent.');
        }

        $invoice->update([
            'status' => InvoiceStatus::SENT,
        ]);

        $this->auditLogService->log('invoice_sent', $invoice);

        return $invoice->fresh();
    }

    /**
     * Cancel an invoice.
     * If linked to a milestone, unlinks it and reverts milestone status.
     */
    public function cancelInvoice(Invoice $invoice): Invoice
    {
        if ($invoice->status === InvoiceStatus::PAID) {
            throw new InvalidArgumentException('Cannot cancel a paid invoice.');
        }

        return DB::transaction(function () use ($invoice) {
            // Unlink from milestone if exists
            $milestone = ContractMilestone::where('invoice_id', $invoice->id)->first();
            if ($milestone) {
                $milestone->update([
                    'invoice_id' => null,
                    'status' => MilestoneStatus::DUE,
                ]);
            }

            $invoice->update([
                'status' => InvoiceStatus::CANCELLED,
            ]);

            $this->auditLogService->log('invoice_cancelled', $invoice);

            return $invoice->fresh();
        });
    }

    /**
     * Link existing invoice to milestone.
     */
    public function linkToMilestone(Invoice $invoice, ContractMilestone $milestone): Invoice
    {
        if ($milestone->invoice_id) {
            throw new InvalidArgumentException('Milestone already has an invoice linked.');
        }

        if ($invoice->status === InvoiceStatus::CANCELLED) {
            throw new InvalidArgumentException('Cannot link cancelled invoice to milestone.');
        }

        $milestone->update([
            'invoice_id' => $invoice->id,
            'status' => $invoice->status === InvoiceStatus::PAID
                ? MilestoneStatus::PAID
                : MilestoneStatus::INVOICED,
            'paid_at' => $invoice->paid_at,
        ]);

        $this->auditLogService->log('invoice_linked_to_milestone', $invoice, [
            'milestone_id' => $milestone->id,
        ]);

        return $invoice->fresh();
    }

    /**
     * Mark overdue invoices (called by scheduled job).
     *
     * Requirements: 3.8, 18.2, 18.3
     */
    public function markOverdueInvoices(): int
    {
        $overdueInvoices = Invoice::withoutGlobalScopes()
            ->with(['project.client'])
            ->where('status', InvoiceStatus::SENT)
            ->where('due_date', '<', Carbon::today())
            ->get();

        $count = 0;
        foreach ($overdueInvoices as $invoice) {
            $invoice->update(['status' => InvoiceStatus::OVERDUE]);

            // Send overdue notification to client
            if ($invoice->project && $invoice->project->client?->user) {
                $invoice->project->client->user->notify(new \App\Modules\Notification\Notifications\InvoiceOverdue(
                    $invoice,
                    $invoice->due_date->diffInDays(Carbon::today())
                ));
            }

            $count++;
        }

        return $count;
    }
}
