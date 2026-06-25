<?php

namespace App\Modules\Project\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProjectSalesDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'documentId' => $this->resource['documentId'],
            'projectId' => $this->resource['projectId'],
            'name' => $this->resource['name'],
            'category' => $this->resource['category'],
            'status' => $this->resource['status'],
            'fileUrl' => $this->resolveFileUrl($this->resource['filePath']),
            'createdAt' => $this->resource['createdAt']?->toISOString() ?? '',
        ];
    }

    private function resolveFileUrl(?string $filePath): ?string
    {
        if ($filePath === null || $filePath === '') {
            return null;
        }

        if (str_starts_with($filePath, 'http://') || str_starts_with($filePath, 'https://') || str_starts_with($filePath, '/')) {
            return $filePath;
        }

        return Storage::disk('public')->url($filePath);
    }
}
