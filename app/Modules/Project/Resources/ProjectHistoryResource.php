<?php

namespace App\Modules\Project\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'historyId' => $this->id,
            'projectId' => $this->project_id ?? 0,
            'fieldName' => $this->field_name ?? '',
            'oldValue' => $this->old_value ?? '',
            'newValue' => $this->new_value ?? '',
            'reason' => $this->reason ?? '',
            'changedBy' => $this->changed_by ?? 0,
            'changedByUser' => $this->whenLoaded('changedByUser', function () {
                return [
                    'userId' => $this->changedByUser->id,
                    'name' => $this->changedByUser->name ?? '',
                    'email' => $this->changedByUser->email ?? '',
                ];
            }),
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
        ];
    }
}
