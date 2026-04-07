<?php

namespace App\Http\Resources\V1\Invoices;

use App\Http\Resources\V1\Projects\ProjectResource;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Annotations as OA;

/**
 * @mixin Invoice
 *
 * @OA\Schema(
 *     schema="InvoiceResource",
 *     type="object",
 *
 *     @OA\Property(property="invoiceId", type="integer", example=1),
 *     @OA\Property(property="projectId", type="integer", example=1),
 *     @OA\Property(property="invoiceNumber", type="string", example="INV-2026-000001"),
 *     @OA\Property(property="amount", type="number", format="float", example=5000.00),
 *     @OA\Property(property="currency", type="string", example="USD"),
 *     @OA\Property(property="status", type="string", example="sent"),
 *     @OA\Property(property="statusLabel", type="string", example="Sent"),
 *     @OA\Property(property="dueDate", type="string", format="date", example="2026-03-01"),
 *     @OA\Property(property="paidAt", type="string", format="date-time", nullable=true, example=null),
 *     @OA\Property(property="notes", type="string", example="Invoice for project kickoff milestone"),
 *     @OA\Property(property="isOverdue", type="boolean", example=false),
 *     @OA\Property(property="isPayable", type="boolean", example=true),
 *     @OA\Property(property="createdBy", type="integer", example=1),
 *     @OA\Property(property="updatedBy", type="integer", example=1),
 *     @OA\Property(property="createdAt", type="string", format="date-time", example="2026-02-01T10:00:00Z"),
 *     @OA\Property(property="updatedAt", type="string", format="date-time", example="2026-02-01T10:00:00Z"),
 *     @OA\Property(
 *         property="project",
 *         type="object",
 *         @OA\Property(property="projectId", type="integer", example=1),
 *         @OA\Property(property="title", type="string", example="E-commerce Website"),
 *         @OA\Property(property="status", type="string", example="in_progress")
 *     )
 * )
 */
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
        /** @var Invoice $invoice */
        $invoice = $this->resource;

        return [
            'invoiceId' => $invoice->id,
            'projectId' => $invoice->project_id,
            'invoiceNumber' => $invoice->invoice_number,
            'amount' => (float) $invoice->amount,
            'currency' => $invoice->currency,
            'status' => $invoice->status->value,
            'statusLabel' => $invoice->status->label(),
            'dueDate' => $invoice->due_date->toDateString(),
            'paidAt' => $invoice->paid_at?->toISOString() ?? '',
            'notes' => $invoice->notes ?? '',
            'isOverdue' => $invoice->isOverdue(),
            'isPayable' => $invoice->isPayable(),
            'createdBy' => $invoice->created_by ?? 0,
            'updatedBy' => $invoice->updated_by ?? 0,
            'createdAt' => $invoice->created_at->toISOString(),
            'updatedAt' => $invoice->updated_at->toISOString(),
            // Include project relationship when loaded
            'project' => $this->whenLoaded('project', function () use ($invoice) {
                return new ProjectResource($invoice->project);
            }),
        ];
    }
}
