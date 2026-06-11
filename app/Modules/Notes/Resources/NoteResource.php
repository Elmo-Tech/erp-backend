<?php

namespace App\Modules\Notes\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'noteId' => $this->id,
            'modelType' => $this->model_type,
            'modelId' => $this->model_id,
            'body' => $this->body,
            'notedAt' => $this->noted_at?->toISOString(),
            'author' => $this->whenLoaded('author', fn () => $this->author ? [
                'userId' => $this->author->id,
                'name' => $this->author->name,
                'email' => $this->author->email,
            ] : null),
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'updatedAt' => $this->updated_at?->toISOString() ?? '',
        ];
    }
}
