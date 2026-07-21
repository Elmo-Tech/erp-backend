<?php

namespace App\Modules\Lead\Controllers;

use App\Enums\ProposalStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\Proposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Reads proposals and handles Kanban pipeline status updates.
 */
class LeadProposalController extends Controller
{
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

    /** Update proposal approval state from the Kanban pipeline. */
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
            'proposal_source' => $p->proposal_source,
            'template_id' => $p->template_id,
            'validity_date' => $p->validity_date?->toDateString(),
            'notes' => $p->notes,
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
