<?php

namespace App\Http\Resources\V1\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
        return [
            'invoiceId' => $this->id ?? 0,
            'invoiceNumber' => $this->invoice_number ?? '',
            'projectId' => $this->project_id ?? 0,
            'amount' => (float) ($this->amount ?? 0.0),
            'currency' => $this->currency ?? 'USD',
            'status' => $this->status?->value ?? '',
            'statusLabel' => $this->status?->label() ?? '',
            'dueDate' => $this->due_date?->toDateString() ?? '',
            'paidAt' => $this->paid_at?->toISOString() ?? '',
            'isOverdue' => $this->isOverdue(),
            'isPayable' => $this->isPayable(),
            'createdAt' => $this->created_at?->toISOString() ?? '',
            // HIDDEN: notes, createdBy, updatedBy
        ];
    }
}