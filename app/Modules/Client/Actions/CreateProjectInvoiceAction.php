<?php

namespace App\Modules\Client\Actions;

use App\Enums\InvoiceStatus;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Project\Models\Project;

class CreateProjectInvoiceAction
{
    public function __construct(
        private LogClientAuditAction $auditLogAction
    ) {}

    /**
     * @param  array{amount:mixed,currency?:string,dueDate:mixed,notes?:string|null}  $data
     */
    public function execute(Project $project, array $data): Invoice
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

        $this->auditLogAction->log('invoice_created', $invoice);

        return $invoice;
    }
}
