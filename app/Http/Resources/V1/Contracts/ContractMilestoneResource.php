<?php

namespace App\Http\Resources\V1\Contracts;

use App\Http\Resources\V1\Invoices\InvoiceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractMilestoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'milestoneId' => $this->id,
            'contractId' => $this->contract_id,
            'title' => $this->title ?? '',
            'description' => $this->description ?? '',
            'amount' => (float) ($this->amount ?? 0),
            'percentage' => (float) ($this->percentage ?? 0),
            'dueDate' => $this->due_date?->format('Y-m-d') ?? '',
            'triggerType' => $this->trigger_type?->value ?? '',
            'triggerTypeLabel' => $this->trigger_type?->label() ?? '',
            'triggerValue' => $this->trigger_value ?? '',
            'status' => $this->status?->value ?? '',
            'statusLabel' => $this->status?->label() ?? '',
            'statusColor' => $this->status?->color() ?? '',
            'invoiceId' => $this->invoice_id ?? 0,
            'paidAt' => $this->paid_at?->toISOString() ?? '',
            'sortOrder' => $this->sort_order ?? 0,
            'isOverdue' => $this->isOverdue(),
            'invoice' => $this->whenLoaded('invoice', function () {
                return $this->invoice ? new InvoiceResource($this->invoice) : null;
            }),
        ];
    }
}
