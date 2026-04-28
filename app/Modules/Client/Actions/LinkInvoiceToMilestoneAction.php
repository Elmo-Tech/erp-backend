<?php

namespace App\Modules\Client\Actions;

use App\Enums\InvoiceStatus;
use App\Enums\MilestoneStatus;
use App\Modules\Contract\Models\ContractMilestone;
use App\Modules\Invoice\Models\Invoice;
use InvalidArgumentException;

class LinkInvoiceToMilestoneAction
{
    public function __construct(
        private LogClientAuditAction $auditLogAction
    ) {}

    public function execute(Invoice $invoice, ContractMilestone $milestone): Invoice
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

        $this->auditLogAction->log('invoice_linked_to_milestone', $invoice, [
            'milestone_id' => $milestone->id,
        ]);

        return $invoice->fresh();
    }
}
