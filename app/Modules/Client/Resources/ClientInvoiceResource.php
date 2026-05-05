<?php

namespace App\Modules\Client\Resources;

use App\Modules\Invoice\Models\Invoice;
use App\Modules\Project\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class ClientInvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Requirements: 5.1, 5.2, 5.3, 5.4
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Invoice $invoice */
        $invoice = $this->resource;

        return [
            'invoiceId' => $invoice->id,
            'invoiceNumber' => $invoice->invoice_number,
            'projectId' => $invoice->model_type === Project::class ? $invoice->model_id : null,
            'modelId' => $invoice->model_id,
            'modelType' => $invoice->model_type,
            'amount' => (float) $invoice->amount,
            'currency' => $invoice->currency,
            'status' => $invoice->status->value,
            'statusLabel' => $invoice->status->label(),
            'dueDate' => $invoice->due_date->toDateString(),
            'paidAt' => $invoice->paid_at?->toISOString() ?? '',
            'isOverdue' => $invoice->isOverdue(),
            'isPayable' => $invoice->isPayable(),
            'createdAt' => $invoice->created_at->toISOString(),
            // HIDDEN: notes, createdBy, updatedBy
        ];
    }
}
