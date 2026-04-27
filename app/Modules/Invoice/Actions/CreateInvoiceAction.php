<?php

namespace App\Modules\Invoice\Actions;

use App\Enums\InvoiceStatus;
use App\Modules\Invoice\Models\Invoice;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

class CreateInvoiceAction
{
    public function __construct(
        private CalculateInvoiceTotalAction $calculateInvoiceTotal,
        private AuditService $auditService
    ) {}

    public function execute(array $data, ?int $actorId): Invoice
    {
        return DB::transaction(function () use ($data, $actorId) {
            $invoice = Invoice::create([
                'project_id' => $data['projectId'],
                'contract_id' => $data['contractId'] ?? null,
                'milestone_name' => $data['milestoneName'] ?? null,
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'amount' => $this->calculateInvoiceTotal->execute($data['amount']),
                'currency' => $data['currency'],
                'status' => InvoiceStatus::DRAFT,
                'due_date' => $data['dueDate'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
            ]);

            $this->auditService->logInvoiceEvent(
                action: 'created',
                invoice: $invoice,
                metadata: [
                    'amount' => $invoice->amount,
                    'currency' => $invoice->currency,
                    'milestone_name' => $invoice->milestone_name,
                ]
            );

            return $invoice->load(['project', 'contract', 'createdBy']);
        });
    }
}
