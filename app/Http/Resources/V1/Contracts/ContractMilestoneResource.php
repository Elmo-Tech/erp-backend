<?php

namespace App\Http\Resources\V1\Contracts;

use App\Http\Resources\V1\Invoices\InvoiceResource;
use App\Models\ContractMilestone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ContractMilestone
 */
class ContractMilestoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ContractMilestone $milestone */
        $milestone = $this->resource;

        return [
            'milestoneId' => $milestone->id,
            'contractId' => $milestone->contract_id,
            'title' => $milestone->title ?? '',
            'description' => $milestone->description ?? '',
            'amount' => (float) ($milestone->amount ?? 0),
            'percentage' => (float) ($milestone->percentage ?? 0),
            'dueDate' => $milestone->due_date?->format('Y-m-d') ?? '',
            'triggerType' => $milestone->trigger_type->value,
            'triggerTypeLabel' => $milestone->trigger_type->label(),
            'triggerValue' => $milestone->trigger_value ?? '',
            'status' => $milestone->status->value,
            'statusLabel' => $milestone->status->label(),
            'statusColor' => $milestone->status->color(),
            'invoiceId' => $milestone->invoice_id ?? 0,
            'paidAt' => $milestone->paid_at?->toISOString() ?? '',
            'sortOrder' => $milestone->sort_order ?? 0,
            'isOverdue' => $milestone->isOverdue(),
            'invoice' => $this->whenLoaded('invoice', function () use ($milestone) {
                return $milestone->invoice ? new InvoiceResource($milestone->invoice) : null;
            }),
        ];
    }
}
