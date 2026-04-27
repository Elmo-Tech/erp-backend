<?php

namespace App\Modules\Client\Actions;

use App\Enums\InvoiceStatus;
use App\Modules\Invoice\Models\Invoice;
use InvalidArgumentException;

class SendInvoiceAction
{
    public function __construct(
        private LogClientAuditAction $auditLogAction
    ) {}

    public function execute(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            throw new InvalidArgumentException('Only draft invoices can be sent.');
        }

        $invoice->update([
            'status' => InvoiceStatus::SENT,
        ]);

        $this->auditLogAction->log('invoice_sent', $invoice);

        return $invoice->fresh();
    }
}
