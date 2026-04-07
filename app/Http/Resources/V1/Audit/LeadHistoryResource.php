<?php

namespace App\Http\Resources\V1\Audit;

use App\Models\LeadHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeadHistory
 */
class LeadHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var LeadHistory $history */
        $history = $this->resource;

        return [
            'historyId' => $history->id,
            'auditableType' => class_basename($history->auditable_type),
            'auditableId' => $history->auditable_id,
            'actionType' => $history->action_type->value,
            'actionTypeLabel' => $history->action_type->label(),
            'actionTypeIcon' => $history->action_type->icon(),
            'actionTypeColor' => $history->action_type->color(),
            'fieldName' => $history->field_name ?? '',
            'fieldLabel' => $history->field_name ? ucwords(str_replace('_', ' ', $history->field_name)) : '',
            'oldValue' => $history->old_value ?? '',
            'newValue' => $history->new_value ?? '',
            'description' => $history->description ?? '',
            'reason' => $history->reason ?? '',
            'metadata' => $history->metadata ?? [],
            'changedBy' => $history->changed_by,
            'changedByUser' => $this->whenLoaded('changedByUser', function () use ($history) {
                return [
                    'userId' => $history->changedByUser->id,
                    'name' => $history->changedByUser->name ?? '',
                    'email' => $history->changedByUser->email ?? '',
                    'avatar' => $history->changedByUser->avatar,
                ];
            }),
            'ipAddress' => $history->ip_address ?? '',
            'userAgent' => $history->user_agent ?? '',
            'createdAt' => $history->created_at?->toISOString() ?? '',
            'createdAtHuman' => $history->created_at?->diffForHumans() ?? '',
        ];
    }
}
