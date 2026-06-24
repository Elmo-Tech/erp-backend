<?php

namespace App\Http\Resources\V1\Project;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isClient = $request->user()?->hasRole('client') ?? false;

        if ($isClient) {
            return [
                'fileId' => $this->id,
                'projectId' => $this->project_id,
                'name' => $this->name ?? '',
                'type' => $this->type ?? 'file',
                'fileUrl' => ($this->is_downloadable || $this->type === 'link') ? $this->file_url : null,
                'mimeType' => $this->mime_type ?? '',
                'size' => $this->size ?? 0,
                'category' => $this->category ?? 'general',
                'isDownloadable' => $this->is_downloadable ?? true,
                'createdAt' => $this->created_at?->toISOString() ?? '',
                'updatedAt' => $this->updated_at?->toISOString() ?? '',
            ];
        }

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
            'isClientVisible' => $this->is_client_visible ?? false,
            'isDownloadable' => $this->is_downloadable ?? true,
            'visibleToRoles' => $this->visible_to_roles ?? [],
            'createdBy' => $this->created_by,
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
        ];
    }
}
