<?php

namespace App\Modules\Project\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChangeRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'changeRequestId' => $this->id,
            'projectId' => $this->project_id,
            'title' => $this->title,
            'description' => $this->description,
            'price' => (float) $this->price,
            'status' => $this->status?->value,
            'statusLabel' => $this->status?->label(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
