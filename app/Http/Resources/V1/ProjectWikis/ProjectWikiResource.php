<?php

namespace App\Http\Resources\V1\ProjectWikis;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectWikiResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'wikiId' => $this->id,
            'projectId' => $this->project_id ?? 0,
            'title' => $this->title ?? '',
            'content' => $this->decrypted_content ?? '',
            'isEncrypted' => (bool) ($this->is_encrypted ?? false),
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
            'createdBy' => $this->created_by ?? 0,
            'updatedBy' => $this->updated_by ?? 0,
        ];
    }
}
