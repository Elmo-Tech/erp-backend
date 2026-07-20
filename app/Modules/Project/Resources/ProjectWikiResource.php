<?php

namespace App\Modules\Project\Resources;

use App\Modules\Project\Models\ProjectWiki;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProjectWiki */
class ProjectWikiResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProjectWiki $wiki */
        $wiki = $this->resource;
        $canViewEncryptedContent = $request->user()?->can('view_encrypted_content') === true;

        return [
            'wikiId' => $wiki->id,
            'projectId' => $wiki->project_id ?? 0,
            'title' => $wiki->title ?? '',
            'content' => $wiki->is_encrypted
                ? ($canViewEncryptedContent ? $wiki->decrypted_content : '')
                : ($wiki->content ?? ''),
            'isEncrypted' => (bool) ($wiki->is_encrypted ?? false),
            'createdAt' => $wiki->created_at?->toISOString() ?? '',
            'updatedAt' => $wiki->updated_at?->toISOString() ?? '',
            'createdBy' => $wiki->created_by ?? 0,
            'updatedBy' => $wiki->updated_by ?? 0,
        ];
    }
}
