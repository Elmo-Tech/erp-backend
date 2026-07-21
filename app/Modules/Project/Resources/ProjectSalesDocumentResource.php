<?php

namespace App\Modules\Project\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'fileUrl' => $this->resource['fileUrl'],
            'createdAt' => $this->resource['createdAt']?->toISOString() ?? '',
        ];
    }
}
