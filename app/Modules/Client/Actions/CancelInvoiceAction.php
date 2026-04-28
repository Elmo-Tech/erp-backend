<?php

namespace App\Modules\Client\Actions;

use App\Enums\InvoiceStatus;
use App\Enums\MilestoneStatus;
use App\Modules\Contract\Models\ContractMilestone;
use App\Modules\Invoice\Models\Invoice;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CancelInvoiceAction
{
    public function __construct(
        private LogClientAuditAction $auditLogAction
    ) {}

    public function execute(Invoice $invoice): Invoice
    {
        if ($invoice->status === InvoiceStatus::PAID) {
            throw new InvalidArgumentException('Cannot cancel a paid invoice.');
        }

        return DB::transaction(function () use ($invoice) {
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

            $this->auditLogAction->log('invoice_cancelled', $invoice);

            return $invoice->fresh();
        });
    }
}
