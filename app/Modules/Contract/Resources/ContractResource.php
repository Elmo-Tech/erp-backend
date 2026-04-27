<?php

namespace App\Modules\Contract\Resources;

use App\Modules\Contract\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contract
 */
class ContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Contract $contract */
        $contract = $this->resource;

        return [
            'contractId' => $contract->id,
            'contractNumber' => $contract->contract_number ?? '',
            'quotationId' => $contract->quotation_id ?? 0,
            'projectId' => $contract->project_id ?? 0,
            'templateId' => $contract->template_id ?? 0,
            'title' => $contract->title ?? '',
            'content' => $contract->content ?? '',
            'partyOne' => $contract->party_one ?? [],
            'partyTwo' => $contract->party_two ?? [],
            'totalAmount' => (float) ($contract->total_amount ?? 0),
            'currency' => $contract->currency ?? 'SAR',
            'paymentTerms' => $contract->payment_terms ?? '',
            'startDate' => $contract->start_date?->format('Y-m-d') ?? '',
            'endDate' => $contract->end_date?->format('Y-m-d') ?? '',
            'signedAt' => $contract->signed_at?->toISOString() ?? '',
            'signedByClient' => $contract->signed_by_client ?? '',
            'status' => $contract->status->value,
            'statusLabel' => $contract->status->label(),
            'statusColor' => $contract->status->color(),
            'createdAt' => $contract->created_at?->toISOString() ?? '',
            'updatedAt' => $contract->updated_at?->toISOString() ?? '',
            'createdBy' => $contract->created_by ?? 0,
            'updatedBy' => $contract->updated_by ?? 0,
            'milestones' => ContractMilestoneResource::collection($this->whenLoaded('milestones')),
            'quotation' => $this->whenLoaded('quotation'),
            'project' => $this->whenLoaded('project'),
        ];
    }
}
