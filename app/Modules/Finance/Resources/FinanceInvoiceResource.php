<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinanceInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'invoiceNumber'  => $this->invoice_number,
            'clientId'       => $this->client_id,
            'projectId'      => $this->project_id,
            'issueDate'      => $this->issue_date?->toDateString(),
            'dueDate'        => $this->due_date?->toDateString(),
            'subtotal'       => (float) $this->subtotal,
            'taxRate'        => (float) $this->tax_rate,
            'taxAmount'      => (float) $this->tax_amount,
            'discountAmount' => (float) $this->discount_amount,
            'totalAmount'    => (float) $this->total_amount,
            'status'         => $this->status,
            'notes'          => $this->notes,

            // Lazy-loaded relations
            'client' => $this->whenLoaded('client', fn () => [
                'id'   => $this->client->id,
                'name' => $this->client->name,
            ]),
            'project' => $this->whenLoaded('project', fn () => [
                'id'    => $this->project->id,
                'title' => $this->project->title,
            ]),
            'items' => $this->whenLoaded(
                'items',
                fn () => FinanceInvoiceItemResource::collection($this->items)
            ),

            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
