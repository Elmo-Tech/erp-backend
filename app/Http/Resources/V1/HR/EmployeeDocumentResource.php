<?php

namespace App\Http\Resources\V1\HR;

use App\Models\EmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeDocumentResource extends JsonResource
{
    /** @mixin EmployeeDocument */
    public function toArray(Request $request): array
    {
        /** @var EmployeeDocument $document */
        $document = $this->resource;

        return [
            'id' => $document->id,
            'employeeId' => $document->employee_id,
            'name' => $document->name,
            'documentType' => $document->document_type,
            'originalFilename' => $document->original_filename,
            'mimeType' => $document->mime_type,
            'size' => $document->size,
            'uploadedBy' => $document->uploaded_by,
            'createdAt' => $document->created_at?->toISOString(),
        ];
    }
}
