<?php

namespace App\Http\Resources\V1\ChangeRequests;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChangeRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'changeRequestId' => $this->id,
            'crNumber' => $this->cr_number ?? '',
            'projectId' => $this->project_id,
            'requestedBy' => $this->requested_by,
            'requesterName' => $this->whenLoaded('requester', fn() => $this->requester->name ?? ''),
            'title' => $this->title ?? '',
            'description' => $this->description ?? '',
            'impactAnalysis' => $this->impact_analysis ?? '',
            'isBillable' => (bool) $this->is_billable,
            'estimatedHours' => (float) ($this->estimated_hours ?? 0),
            'estimatedCost' => (float) ($this->estimated_cost ?? 0),
            'approvedCost' => (float) ($this->approved_cost ?? 0),
            'additionalDays' => (int) ($this->additional_days ?? 0),
            'status' => $this->status?->value ?? '',
            'statusLabel' => $this->status?->label() ?? '',
            'statusColor' => $this->status?->color() ?? '',
            'approvedBy' => $this->approved_by ?? 0,
            'approverName' => $this->whenLoaded('approver', fn() => $this->approver->name ?? ''),
            'approvedAt' => $this->approved_at?->toISOString() ?? '',
            'rejectionReason' => $this->rejection_reason ?? '',
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
            'createdBy' => $this->created_by ?? 0,
            'updatedBy' => $this->updated_by ?? 0,
        ];
    }
}
