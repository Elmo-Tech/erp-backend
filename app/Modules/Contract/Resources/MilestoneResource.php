<?php

namespace App\Modules\Contract\Resources;

use App\Modules\Invoice\Resources\Invoices\InvoiceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="MilestoneResource",
 *     type="object",
 *
 *     @OA\Property(property="milestoneId", type="integer", example=1),
 *     @OA\Property(property="contractId", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="Design Phase Payment"),
 *     @OA\Property(property="description", type="string", example="Payment for completing the design phase"),
 *     @OA\Property(property="amount", type="number", format="float", example=5000.00),
 *     @OA\Property(property="percentage", type="number", format="float", example=30.00),
 *     @OA\Property(property="dueDate", type="string", format="date", example="2025-02-15"),
 *     @OA\Property(property="triggerType", type="string", enum={"immediate", "project_status", "deliverable", "date"}, example="project_status"),
 *     @OA\Property(property="triggerValue", type="string", example="design_approved"),
 *     @OA\Property(property="status", type="string", enum={"pending", "due", "invoiced", "paid", "overdue"}, example="due"),
 *     @OA\Property(property="invoiceId", type="integer", nullable=true, example=5),
 *     @OA\Property(property="paidAt", type="string", format="date-time", nullable=true, example="2025-02-10T14:30:00Z"),
 *     @OA\Property(property="sortOrder", type="integer", example=1),
 *     @OA\Property(property="invoice", ref="#/components/schemas/InvoiceResource", nullable=true),
 *     @OA\Property(property="createdAt", type="string", format="date-time", example="2025-01-01T10:00:00Z"),
 *     @OA\Property(property="updatedAt", type="string", format="date-time", example="2025-01-05T15:30:00Z")
 * )
 */
class MilestoneResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'milestoneId' => $this->id,
            'contractId' => $this->contract_id,
            'title' => $this->title ?? '',
            'description' => $this->description ?? '',
            'amount' => (float) $this->amount,
            'percentage' => (float) $this->percentage,
            'dueDate' => $this->due_date?->format('Y-m-d'),
            'triggerType' => $this->trigger_type?->value ?? $this->trigger_type,
            'triggerValue' => $this->trigger_value ?? '',
            'status' => $this->status?->value ?? $this->status,
            'invoiceId' => $this->invoice_id,
            'paidAt' => $this->paid_at?->toISOString(),
            'sortOrder' => $this->sort_order ?? 0,
            'invoice' => $this->whenLoaded('invoice', function () {
                return $this->invoice ? new InvoiceResource($this->invoice) : null;
            }),
            'contract' => $this->whenLoaded('contract', function () {
                return [
                    'contractId' => $this->contract->id,
                    'contractNumber' => $this->contract->contract_number,
                    'title' => $this->contract->title,
                    'currency' => $this->contract->currency,
                    'projectId' => $this->contract->project_id,
                ];
            }),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
