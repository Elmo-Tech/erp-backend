<?php

namespace App\Modules\ProjectHandover\Resources;

use App\Modules\ProjectHandover\Models\ChangeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChangeRequest
 */
class ChangeRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ChangeRequest $changeRequest */
        $changeRequest = $this->resource;

        return [
            'changeRequestId' => $changeRequest->id,
            'crNumber' => $changeRequest->cr_number ?? '',
            'projectId' => $changeRequest->project_id,
            'requestedBy' => $changeRequest->requested_by,
            'requesterName' => $this->whenLoaded('requester', fn () => $changeRequest->requester->name ?? ''),
            'title' => $changeRequest->title ?? '',
            'description' => $changeRequest->description ?? '',
            'impactAnalysis' => $changeRequest->impact_analysis ?? '',
            'isBillable' => $changeRequest->is_billable,
            'estimatedHours' => (float) ($changeRequest->estimated_hours ?? 0),
            'estimatedCost' => (float) ($changeRequest->estimated_cost ?? 0),
            'approvedCost' => (float) ($changeRequest->approved_cost ?? 0),
            'additionalDays' => (int) ($changeRequest->additional_days ?? 0),
            'status' => $changeRequest->status->value,
            'statusLabel' => $changeRequest->status->label(),
            'statusColor' => $changeRequest->status->color(),
            'approvedBy' => $changeRequest->approved_by ?? 0,
            'approverName' => $this->whenLoaded('approver', fn () => $changeRequest->approver->name ?? ''),
            'approvedAt' => $changeRequest->approved_at?->toISOString() ?? '',
            'rejectionReason' => $changeRequest->rejection_reason ?? '',
            'createdAt' => $changeRequest->created_at?->toISOString() ?? '',
            'updatedAt' => $changeRequest->updated_at?->toISOString() ?? '',
            'createdBy' => $changeRequest->created_by ?? 0,
            'updatedBy' => $changeRequest->updated_by ?? 0,
        ];
    }
}
