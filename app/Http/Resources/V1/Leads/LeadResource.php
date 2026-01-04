<?php

namespace App\Http\Resources\V1\Leads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'leadId' => $this->id,
            'name' => $this->name ?? '',
            'email' => $this->email ?? '',
            'phone' => $this->phone ?? '',
            'company' => $this->company ?? '',
            'country' => $this->country ?? '',
            'source' => $this->source?->value ?? '',
            'sourceLabel' => $this->source?->label() ?? '',
            'referenceType' => $this->reference_type?->value ?? '',
            'referenceId' => $this->reference_id ?? 0,
            'status' => $this->status?->value ?? '',
            'statusLabel' => $this->status?->label() ?? '',
            'priority' => $this->priority?->value ?? 'medium',
            'priorityLabel' => $this->priority?->label() ?? '',
            'priorityColor' => $this->priority?->color() ?? 'blue',
            'expectedBudget' => (float) ($this->expected_budget ?? 0),
            'expectedCloseDate' => $this->expected_close_date?->format('Y-m-d') ?? '',
            'lastContactAt' => $this->last_contact_at?->toISOString() ?? '',
            'nextFollowUpAt' => $this->next_follow_up_at?->toISOString() ?? '',
            'notes' => $this->notes ?? '',
            'lossReason' => $this->loss_reason ?? '',
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
            'createdBy' => $this->created_by ?? 0,
            'updatedBy' => $this->updated_by ?? 0,
            
            // Relations (when loaded)
            'activities' => LeadActivityResource::collection($this->whenLoaded('activities')),
            'meetings' => LeadMeetingResource::collection($this->whenLoaded('meetings')),
            'quotationsCount' => $this->whenLoaded('quotations', fn() => $this->quotations->count()),
        ];
    }
}