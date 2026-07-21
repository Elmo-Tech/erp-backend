<?php

namespace App\Modules\Lead\Actions;

use App\Modules\Lead\Models\Lead;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class StoreLeadDocumentFileAction
{
    /**
     * @return array{file_path: string, file_name: string, file_mime_type: string|null, file_size: int}
     */
    public function execute(Lead $lead, UploadedFile $file, string $documentType): array
    {
        $path = $file->store("leads/{$lead->id}/sales-documents/{$documentType}", 'local');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Unable to store the sales document.');
        }

        return [
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ];
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }
}
