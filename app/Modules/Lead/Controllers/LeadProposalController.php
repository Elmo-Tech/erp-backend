<?php

namespace App\Modules\Lead\Controllers;

use App\Enums\ProposalStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Lead\Actions\StoreLeadDocumentFileAction;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\Proposal;
use App\Modules\Lead\Requests\StoreLeadProposalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Manages proposals attached directly to a lead from the Details Page.
 *
 * These endpoints accept the EXACT same payload shape as the pipeline's
 * stage_results[*] entry for type=proposal, so the frontend reuses the
 * same ProposalDirectUploadTab form and payload mapper without changes.
 */
class LeadProposalController extends Controller
{
    public function __construct(
        private readonly StoreLeadDocumentFileAction $storeDocumentFile,
    ) {}

    /**
     * List all proposals for a lead.
     *
     * GET /api/v1/leads/{lead}/proposals
     */
    public function index(Lead $lead): JsonResponse
    {
        $proposals = $lead->proposals()
            ->with('assignee:id,name,email,avatar')
            ->orderByDesc('created_at')
            ->get();

        $data = $proposals->map(fn (Proposal $p) => $this->formatProposal($p));

        return ApiResponse::success($data, 'Proposals retrieved successfully.');
    }

    /**
     * Create a new proposal directly on a lead (bypasses the pipeline drag).
     *
     * POST /api/v1/leads/{lead}/proposals
     *
     * Payload shape mirrors stage_results[*] for type=proposal so the
     * frontend ProposalDirectUploadTab + mapStageResultToPayload can be
     * reused without modification.
     */
    public function store(StoreLeadProposalRequest $request, Lead $lead): JsonResponse
    {
        $data = $request->validated();
        $fileAttributes = $request->hasFile('upload_file')
            ? $this->storeDocumentFile->execute($lead, $request->file('upload_file'), 'proposal')
            : [];

        try {
            $proposal = Proposal::create(array_merge([
                'lead_id' => $lead->id,
                'client_id' => $lead->client_id,
                'project_id' => null,
                'status' => $data['status'] ?? ProposalStatus::DRAFT->value,
                'creation_type' => $data['creation_type'],
                'skip_reason' => $data['skip_reason'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
            ], $fileAttributes));
        } catch (Throwable $exception) {
            $this->storeDocumentFile->delete($fileAttributes['file_path'] ?? null);

            throw $exception;
        }

        $proposal->load('assignee:id,name,email,avatar');

        return ApiResponse::success(
            $this->formatProposal($proposal),
            'Proposal created successfully.',
            201
        );
    }

    /**
     * Update editable proposal fields from the lead details table.
     */
    public function update(Request $request, Lead $lead, Proposal $proposal): JsonResponse
    {
        $this->assertBelongsToLead($proposal, $lead);

        $data = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(ProposalStatus::values())],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $proposal->update($data);
        $proposal->load('assignee:id,name,email,avatar');

        return ApiResponse::success(
            $this->formatProposal($proposal),
            'Proposal updated successfully.'
        );
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private function formatProposal(Proposal $p): array
    {
        return [
            'id' => $p->id,
            'status' => $p->status->value,
            'creation_type' => $p->creation_type->value,
            'skip_reason' => $p->skip_reason,
            'file_path' => $p->file_path ? route('leads.documents.download', [
                'lead' => $p->lead_id,
                'documentType' => 'proposal',
                'documentId' => $p->id,
            ]) : null,
            'file_name' => $p->file_name,
            'file_mime_type' => $p->file_mime_type,
            'file_size' => $p->file_size,
            'assigned_to' => $p->assigned_to,
            'personal_task_id' => $p->personal_task_id,
            'assignee' => $p->assignee ? [
                'id' => $p->assignee->id,
                'name' => $p->assignee->name,
                'email' => $p->assignee->email,
                'avatar' => $p->assignee->avatar,
            ] : null,
            'created_at' => $p->created_at?->toISOString(),
            'updated_at' => $p->updated_at?->toISOString(),
        ];
    }

    private function assertBelongsToLead(Proposal $proposal, Lead $lead): void
    {
        if ((int) $proposal->lead_id !== (int) $lead->id) {
            abort(404, 'Proposal not found for this lead.');
        }
    }
}
