<?php

namespace App\Modules\Lead\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Lead\Models\Contract;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\Proposal;
use App\Modules\Lead\Models\Quotation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadDocumentDownloadController extends Controller
{
    public function download(Request $request, Lead $lead, string $documentType, int $documentId): JsonResponse|StreamedResponse
    {
        $document = $this->authorizedDocument($request, $documentType, $documentId);

        if ((int) $document->lead_id !== $lead->id || ! $document->file_path || ! Storage::disk('local')->exists($document->file_path)) {
            return ApiResponse::error('Document file not found.', 404);
        }

        return Storage::disk('local')->download(
            $document->file_path,
            $document->file_name ?: basename($document->file_path)
        );
    }

    private function authorizedDocument(Request $request, string $documentType, int $documentId): Model
    {
        $documentAccess = match ($documentType) {
            'proposal' => [Proposal::class, ['view_leads', 'manage_leads']],
            'quotation' => [Quotation::class, ['view_quotations', 'manage_leads']],
            'contract' => [Contract::class, ['view_contracts', 'manage_contracts']],
            default => abort(404, 'Sales document not found.'),
        };

        abort_unless($request->user()?->canAny($documentAccess[1]), 403, 'Unauthorized');

        return $documentAccess[0]::query()->findOrFail($documentId);
    }
}
