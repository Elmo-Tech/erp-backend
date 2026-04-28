<?php

namespace App\Modules\Client\Actions;

use App\Enums\InvoiceStatus;
use App\Enums\MilestoneStatus;
use App\Modules\Contract\Models\ContractMilestone;
use App\Modules\Invoice\Models\Invoice;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateMilestoneInvoiceAction
{
    public function __construct(
        private LogClientAuditAction $auditLogAction
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(ContractMilestone $milestone, array $data = []): Invoice
    {
        if ($milestone->invoice_id) {
            throw new InvalidArgumentException('Milestone already has an invoice linked.');
        }

        if ($milestone->status === MilestoneStatus::PAID) {
            throw new InvalidArgumentException('Cannot create invoice for paid milestone.');
        }

        $contract = $milestone->contract;
        if (! $contract->project_id) {
            throw new InvalidArgumentException('Contract must be linked to a project to create invoice.');
        }

        return DB::transaction(function () use ($milestone, $contract, $data) {
            $invoice = Invoice::create([
                'project_id' => $contract->project_id,
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'amount' => $milestone->amount,
                'currency' => $contract->currency,
                'status' => InvoiceStatus::DRAFT,
                'due_date' => $data['dueDate'] ?? $milestone->due_date ?? now()->addDays(14),
                'notes' => $data['notes'] ?? "Payment for milestone: {$milestone->title}",
            ]);

            $milestone->update([
                'invoice_id' => $invoice->id,
                'status' => MilestoneStatus::INVOICED,
            ]);

            $this->auditLogAction->log('invoice_created_from_milestone', $invoice, [
                'milestone_id' => $milestone->id,
                'milestone_title' => $milestone->title,
                'contract_id' => $contract->id,
            ]);

            return $invoice->load('project');
        });
    }
}
