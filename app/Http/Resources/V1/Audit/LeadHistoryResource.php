<?php

namespace App\Http\Resources\V1\Audit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'historyId' => $this->id,
            'auditableType' => class_basename($this->auditable_type),
            'auditableId' => $this->auditable_id,
            'actionType' => $this->action_type?->value ?? '',
            'actionTypeLabel' => $this->action_type?->label() ?? '',
            'actionTypeIcon' => $this->action_type?->icon() ?? '',
            'actionTypeColor' => $this->action_type?->color() ?? '',
            'fieldName' => $this->field_name ?? '',
            'fieldLabel' => $this->field_name ? ucwords(str_replace('_', ' ', $this->field_name)) : '',
            'oldValue' => $this->old_value ?? '',
            'newValue' => $this->new_value ?? '',
            'description' => $this->description ?? '',
            'reason' => $this->reason ?? '',
            'metadata' => $this->metadata ?? [],
            'changedBy' => $this->changed_by,
            'changedByUser' => $this->whenLoaded('changedByUser', function () {
                return [
                    'userId' => $this->changedByUser->id,
                    'name' => $this->changedByUser->name ?? '',
                    'email' => $this->changedByUser->email ?? '',
                    'avatar' => $this->changedByUser->avatar ?? '',
                ];
            }),
            'ipAddress' => $this->ip_address ?? '',
            'userAgent' => $this->user_agent ?? '',
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'createdAtHuman' => $this->created_at?->diffForHumans() ?? '',
        ];
    }
}
