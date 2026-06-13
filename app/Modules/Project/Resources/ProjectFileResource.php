<?php

namespace App\Modules\Project\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'fileId' => $this->id,
            'projectId' => $this->project_id,
            'name' => $this->name ?? '',
            'type' => $this->type ?? 'file',
            'fileUrl' => $this->file_url,
            'mimeType' => $this->mime_type ?? '',
            'size' => $this->size ?? 0,
            'description' => $this->description ?? '',
            'category' => $this->category ?? 'general',
            'createdAt' => $this->created_at?->toISOString() ?? '',
        ];
    }
}
