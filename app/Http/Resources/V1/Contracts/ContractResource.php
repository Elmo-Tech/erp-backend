<?php

namespace App\Http\Resources\V1\Contracts;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'contractId' => $this->id,
            'contractNumber' => $this->contract_number ?? '',
            'quotationId' => $this->quotation_id ?? 0,
            'projectId' => $this->project_id ?? 0,
            'templateId' => $this->template_id ?? 0,
            'title' => $this->title ?? '',
            'content' => $this->content ?? '',
            
            'partyOne' => $this->party_one ?? [],
            'partyTwo' => $this->party_two ?? [],
            
            'totalAmount' => (float) ($this->total_amount ?? 0),
            'currency' => $this->currency ?? 'SAR',
            'paymentTerms' => $this->payment_terms ?? '',
            
            'startDate' => $this->start_date?->format('Y-m-d') ?? '',
            'endDate' => $this->end_date?->format('Y-m-d') ?? '',
            'signedAt' => $this->signed_at?->toISOString() ?? '',
            'signedByClient' => $this->signed_by_client ?? '',
            
            'status' => $this->status?->value ?? '',
            'statusLabel' => $this->status?->label() ?? '',
            'statusColor' => $this->status?->color() ?? '',
            
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
            'createdBy' => $this->created_by ?? 0,
            'updatedBy' => $this->updated_by ?? 0,
            
            // Relations
            'milestones' => ContractMilestoneResource::collection($this->whenLoaded('milestones')),
            'quotation' => $this->whenLoaded('quotation'),
            'project' => $this->whenLoaded('project'),
        ];
    }
}
