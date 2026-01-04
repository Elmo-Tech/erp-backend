<?php

namespace App\Http\Resources\V1\Leads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'activityId' => $this->id,
            'leadId' => $this->lead_id,
            'userId' => $this->user_id,
            'userName' => $this->whenLoaded('user', fn() => $this->user->name ?? ''),
            'type' => $this->type?->value ?? '',
            'typeLabel' => $this->type?->label() ?? '',
            'typeIcon' => $this->type?->icon() ?? '',
            'title' => $this->title ?? '',
            'description' => $this->description ?? '',
            'outcome' => $this->outcome ?? '',
            'scheduledAt' => $this->scheduled_at?->toISOString() ?? '',
            'completedAt' => $this->completed_at?->toISOString() ?? '',
            'createdAt' => $this->created_at?->toISOString() ?? '',
        ];
    }
}
