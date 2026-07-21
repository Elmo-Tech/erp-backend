<?php

namespace App\Modules\Lead\Controllers;

use App\Enums\QuotationStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Lead\Actions\StoreLeadDocumentFileAction;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\Quotation;
use App\Modules\Lead\Requests\StoreLeadQuotationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Manages quotations attached directly to a lead from the Details Page.
 *
 * Payload shape mirrors stage_results[*] for type=quotation so the
 * frontend QuotationDirectUploadTab + mapStageResultToPayload can be
 * reused without modification.
 */
class LeadQuotationController extends Controller
{
    public function __construct(
        private readonly StoreLeadDocumentFileAction $storeDocumentFile,
    ) {}

    /**
     * List all quotations for a lead.
     *
     * GET /api/v1/leads/{lead}/quotations
     */
    public function index(Request $request, Lead $lead): JsonResponse
    {
        abort_unless($request->user()?->canAny(['view_quotations', 'manage_leads']), 403, 'Unauthorized');

        $quotations = $lead->quotations()
            ->with('assignee:id,name,email,avatar')
            ->orderByDesc('created_at')
            ->get();

        $data = $quotations->map(fn (Quotation $q) => $this->formatQuotation($q));

        return ApiResponse::success($data, 'Quotations retrieved successfully.');
    }

    /**
     * Create a new quotation directly on a lead.
     *
     * POST /api/v1/leads/{lead}/quotations
     */
    public function store(StoreLeadQuotationRequest $request, Lead $lead): JsonResponse
    {
        abort_unless($request->user()?->canAny(['create_quotations', 'manage_leads']), 403, 'Unauthorized');

        $data = $request->validated();
        $fileAttributes = $request->hasFile('upload_file')
            ? $this->storeDocumentFile->execute($lead, $request->file('upload_file'), 'quotation')
            : [];

        try {
            $quotation = Quotation::create(array_merge([
                'lead_id' => $lead->id,
                'client_id' => $lead->client_id,
                'project_id' => null,
                'status' => $data['status'] ?? QuotationStatus::DRAFT->value,
                'creation_type' => $data['creation_type'],
                'skip_reason' => $data['skip_reason'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
                'currency' => $data['currency'] ?? 'USD',
                'base_price' => $data['base_price'] ?? null,
                'commission_type' => $data['commission_type'] ?? null,
                'final_total' => $data['final_total'] ?? null,
                'payment_stages' => $data['payment_stages'] ?? null,
            ], $fileAttributes));
        } catch (Throwable $exception) {
            $this->storeDocumentFile->delete($fileAttributes['file_path'] ?? null);

            throw $exception;
        }

        $quotation->load('assignee:id,name,email,avatar');

        return ApiResponse::success(
            $this->formatQuotation($quotation),
            'Quotation created successfully.',
            201
        );
    }

    /**
     * Update editable quotation fields from the lead details table.
     */
    public function update(Request $request, Lead $lead, Quotation $quotation): JsonResponse
    {
        abort_unless($request->user()?->canAny(['edit_quotations', 'manage_leads']), 403, 'Unauthorized');
        $this->assertBelongsToLead($quotation, $lead);

        $data = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(QuotationStatus::values())],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $quotation->update($data);
        $quotation->load('assignee:id,name,email,avatar');

        return ApiResponse::success(
            $this->formatQuotation($quotation),
            'Quotation updated successfully.'
        );
    }

    public function destroy(Request $request, Lead $lead, Quotation $quotation): JsonResponse
    {
        abort_unless($request->user()?->canAny(['delete_quotations', 'manage_leads']), 403, 'Unauthorized');
        $this->assertBelongsToLead($quotation, $lead);

        $quotation->delete();

        return ApiResponse::success(null, 'Quotation deleted successfully.');
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private function formatQuotation(Quotation $q): array
    {
        return [
            'id' => $q->id,
            'status' => $q->status->value,
            'creation_type' => $q->creation_type->value,
            'skip_reason' => $q->skip_reason,
            'file_path' => $q->file_path ? route('leads.documents.download', [
                'lead' => $q->lead_id,
                'documentType' => 'quotation',
                'documentId' => $q->id,
            ]) : null,
            'file_name' => $q->file_name,
            'file_mime_type' => $q->file_mime_type,
            'file_size' => $q->file_size,
            'currency' => $q->currency,
            'base_price' => $q->base_price !== null ? (float) $q->base_price : null,
            'commission_type' => $q->commission_type,
            'final_total' => $q->final_total !== null ? (float) $q->final_total : null,
            'payment_stages' => $q->payment_stages ?? [],
            'assigned_to' => $q->assigned_to,
            'personal_task_id' => $q->personal_task_id,
            'assignee' => $q->assignee ? [
                'id' => $q->assignee->id,
                'name' => $q->assignee->name,
                'email' => $q->assignee->email,
                'avatar' => $q->assignee->avatar,
            ] : null,
            'created_at' => $q->created_at?->toISOString(),
            'updated_at' => $q->updated_at?->toISOString(),
        ];
    }

    private function assertBelongsToLead(Quotation $quotation, Lead $lead): void
    {
        if ((int) $quotation->lead_id !== (int) $lead->id) {
            abort(404, 'Quotation not found for this lead.');
        }
    }
}
