<?php

namespace App\Http\Resources\V1\Invoices;

use App\Http\Resources\V1\Projects\ProjectResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Return all invoice fields in camelCase format.
     * 
     * Requirements: 3.7, 10.2
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'invoiceId' => $this->id ?? 0,
            'projectId' => $this->project_id ?? 0,
            'invoiceNumber' => $this->invoice_number ?? '',
            'amount' => (float) ($this->amount ?? 0.0),
            'currency' => $this->currency ?? 'USD',
            'status' => $this->status?->value ?? '',
            'statusLabel' => $this->status?->label() ?? '',
            'dueDate' => $this->due_date?->toDateString() ?? '',
            'paidAt' => $this->paid_at?->toISOString() ?? '',
            'notes' => $this->notes ?? '',
            'isOverdue' => $this->isOverdue(),
            'isPayable' => $this->isPayable(),
            'createdBy' => $this->created_by ?? 0,
            'updatedBy' => $this->updated_by ?? 0,
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
            // Include project relationship when loaded
            'project' => $this->whenLoaded('project', function () {
                return new ProjectResource($this->project);
            }),
        ];
    }
}