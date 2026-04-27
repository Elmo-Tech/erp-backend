<?php

namespace App\Modules\Client\Actions;

use App\Enums\InvoiceStatus;
use App\Enums\MilestoneStatus;
use App\Models\ContractMilestone;
use App\Modules\Invoice\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MarkInvoiceAsPaidAction
{
    public function __construct(
        private LogClientAuditAction $auditLogAction
    ) {}

    public function execute(Invoice $invoice): Invoice
    {
        if (! $invoice->isPayable()) {
            throw new InvalidArgumentException('Invoice cannot be paid in current status.');
        }

        return DB::transaction(function () use ($invoice) {
            $invoice->update([
                'status' => InvoiceStatus::PAID,
                'paid_at' => Carbon::now(),
            ]);

            $milestone = ContractMilestone::where('invoice_id', $invoice->id)->first();
            if ($milestone) {
                $milestone->update([
                    'status' => MilestoneStatus::PAID,
                    'paid_at' => Carbon::now(),
                ]);

                $this->auditLogAction->log('milestone_paid_via_invoice', $invoice, [
                    'milestone_id' => $milestone->id,
                    'milestone_title' => $milestone->title,
                ]);
            }

            $this->auditLogAction->log('invoice_paid', $invoice);

            return $invoice->fresh();
        });
    }
}
