<?php

namespace App\Http\Resources\V1\Invoice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="InvoiceResource",
 *     type="object",
 *     @OA\Property(property="invoiceId", type="integer", example=1),
 *     @OA\Property(property="projectId", type="integer", example=1),
 *     @OA\Property(property="contractId", type="integer", example=1),
 *     @OA\Property(property="milestoneName", type="string", example="Project Kickoff"),
 *     @OA\Property(property="invoiceNumber", type="string", example="INV-2026-000001"),
 *     @OA\Property(property="amount", type="number", format="float", example=5000.00),
 *     @OA\Property(property="currency", type="string", example="USD"),
 *     @OA\Property(property="status", type="string", example="sent"),
 *     @OA\Property(property="statusLabel", type="string", example="Sent"),
 *     @OA\Property(property="statusColor", type="string", example="warning"),
 *     @OA\Property(property="dueDate", type="string", format="date", example="2026-03-01"),
 *     @OA\Property(property="sentAt", type="string", format="datetime", example="2026-02-01T10:00:00Z"),
 *     @OA\Property(property="paidAt", type="string", format="datetime", example=null),
 *     @OA\Property(property="notes", type="string", example="Invoice for project kickoff milestone"),
 *     @OA\Property(property="isOverdue", type="boolean", example=false),
 *     @OA\Property(property="isPayable", type="boolean", example=true),
 *     @OA\Property(property="daysUntilDue", type="integer", example=28),
 *     @OA\Property(
 *         property="project",
 *         type="object",
 *         @OA\Property(property="projectId", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="E-commerce Website"),
 *         @OA\Property(property="status", type="string", example="in_progress")
 *     ),
 *     @OA\Property(
 *         property="contract",
 *         type="object",
 *         @OA\Property(property="contractId", type="integer", example=1),
 *         @OA\Property(property="contractNumber", type="string", example="CON-2026-000001"),
 *         @OA\Property(property="totalAmount", type="number", format="float", example=20000.00)
 *     ),
 *     @OA\Property(property="createdAt", type="string", format="datetime", example="2026-02-01T10:00:00Z"),
 *     @OA\Property(property="updatedAt", type="string", format="datetime", example="2026-02-01T10:00:00Z"),
 *     @OA\Property(property="createdBy", type="string", example="John Doe"),
 *     @OA\Property(property="updatedBy", type="string", example="Jane Smith")
 * )
 */
class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'invoiceId' => $this->id,
            'projectId' => $this->project_id,
            'contractId' => $this->contract_id,
            'milestoneName' => $this->milestone_name ?? '',
            'invoiceNumber' => $this->invoice_number,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'statusLabel' => __('enums.invoice_status.' . $this->status->value),
            'statusColor' => $this->getStatusColor(),
            'dueDate' => $this->due_date->format('Y-m-d'),
            'sentAt' => $this->sent_at?->toISOString(),
            'paidAt' => $this->paid_at?->toISOString(),
            'notes' => $this->notes ?? '',
            'isOverdue' => $this->isOverdue(),
            'isPayable' => $this->isPayable(),
            'daysUntilDue' => $this->due_date->diffInDays(now(), false),
            
            // Relationships
            'project' => $this->when($this->relationLoaded('project'), function () {
                return [
                    'projectId' => $this->project->id,
                    'name' => $this->project->name,
                    'status' => $this->project->status->value,
                ];
            }),
            
            'contract' => $this->when($this->relationLoaded('contract') && $this->contract, function () {
                return [
                    'contractId' => $this->contract->id,
                    'contractNumber' => $this->contract->contract_number,
                    'totalAmount' => (float) $this->contract->total_amount,
                ];
            }),
            
            // Audit fields
            'createdAt' => $this->created_at->toISOString(),
            'updatedAt' => $this->updated_at->toISOString(),
            'createdBy' => $this->when($this->relationLoaded('createdBy'), $this->createdBy?->name ?? ''),
            'updatedBy' => $this->when($this->relationLoaded('updatedBy'), $this->updatedBy?->name ?? ''),
        ];
    }

    /**
     * Get status color for UI display
     */
    private function getStatusColor(): string
    {
        return match ($this->status->value) {
            'draft' => 'default',
            'sent' => 'warning',
            'paid' => 'success',
            'overdue' => 'destructive',
            'cancelled' => 'destructive',
            default => 'default',
        };
    }
}