<?php

namespace App\Modules\Lead\Controllers;

use App\Enums\LeadStage;
use App\Enums\LeadStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Requests\StoreLeadRequest;
use App\Modules\Meetings\Models\Meeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    /**
     * List all active leads for the pipeline board.
     *
     * GET /api/v1/leads
     *
     * Returns every non-deleted lead ordered by creation date (newest first).
     * The frontend Kanban board uses this to seed the Zustand store on mount.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Lead::query();

        if ($request->filled('search')) {
            $value = (string) $request->input('search');
            $query->where(function ($searchQuery) use ($value) {
                $searchQuery
                    ->where('title', 'like', "%{$value}%")
                    ->orWhere('source', 'like', "%{$value}%")
                    ->orWhere('metadata->company_name', 'like', "%{$value}%")
                    ->orWhere('metadata->primary_email', 'like', "%{$value}%");
            });
        }

        if ($request->filled('stage')) {
            $stage = (string) $request->input('stage');
            $backendStage = $stage === 'new' ? 'new_lead' : ($stage === 'closed_won' ? 'won' : $stage);
            $query->where('stage', $backendStage);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('source')) {
            $query->where('source', $request->input('source'));
        }

        if ($request->filled('assigneeId')) {
            $query->where('assigned_to', (int) $request->input('assigneeId'));
        }

        $leads = $query->orderByDesc('created_at')->get();

        $data = $leads->map(fn (Lead $lead) => [
            'id'              => $lead->id,
            'title'           => $lead->title,
            'stage'           => $lead->stage->value,
            'status'          => $lead->status->value,
            'company'         => $lead->metadata['company_name'] ?? null,
            'email'           => $lead->metadata['primary_email'] ?? null,
            'phone'           => $lead->metadata['primary_phone'] ?? null,
            'expected_budget' => $lead->expected_budget,
            'source'          => $lead->source,
            'assignedTo'      => $lead->assigned_to,
            'hasCompletedMeeting' => $this->hasCompletedMeeting($lead),
            'hasAcceptedProposalOrQuotation' => $this->hasAcceptedProposalOrQuotation($lead),
            'createdAt'       => $lead->created_at?->toISOString(),
            'updatedAt'       => $lead->updated_at?->toISOString(),
        ]);

        return ApiResponse::success($data, 'Leads retrieved successfully.');
    }

    /**
     * Retrieve a single lead by its ID.
     *
     * GET /api/v1/leads/{lead}
     *
     * Uses Laravel's implicit route model binding — the {lead} segment is
     * automatically resolved to a Lead instance (soft-deleted records return 404).
     * The assignee relationship is eager-loaded so the details page can display
     * the assigned user's name and avatar without a second request.
     */
    public function show(Lead $lead): JsonResponse
    {
        $lead->load('assignee:id,name,email,avatar');

        return ApiResponse::success(
            [
                'id'              => $lead->id,
                'title'           => $lead->title,
                'stage'           => $lead->stage->value,
                'status'          => $lead->status->value,
                'company'         => $lead->metadata['company_name'] ?? null,
                'email'           => $lead->metadata['primary_email'] ?? null,
                'phone'           => $lead->metadata['primary_phone'] ?? null,
                'contact_name'    => $lead->metadata['contact_name'] ?? null,
                'expected_budget' => $lead->expected_budget,
                'source'          => $lead->source,
                'notes'           => $lead->notes,
                'expected_close_date' => $lead->expected_close_date?->toDateString(),
                'assignedTo'      => $lead->assigned_to,
                'hasCompletedMeeting' => $this->hasCompletedMeeting($lead),
                'hasAcceptedProposalOrQuotation' => $this->hasAcceptedProposalOrQuotation($lead),
                'assignee'        => $lead->assignee ? [
                    'id'     => $lead->assignee->id,
                    'name'   => $lead->assignee->name,
                    'email'  => $lead->assignee->email,
                    'avatar' => $lead->assignee->avatar,
                ] : null,
                'createdAt'       => $lead->created_at?->toISOString(),
                'updatedAt'       => $lead->updated_at?->toISOString(),
            ],
            'Lead retrieved successfully.'
        );
    }

    /**
     * Create a new lead and place it in the first pipeline stage.
     *
     * POST /api/v1/leads
     *
     * The lead is always created at stage `new_lead` regardless of what
     * the client sends — stage progression is handled exclusively by the
     * drag-and-drop pipeline engine.
     *
     * The `company_name`, `contact_name`, `primary_email`, and
     * `primary_phone` fields are stored in the lead's `metadata` JSON
     * column because the Lead model does not have dedicated columns for
     * them (those live on the Client model after conversion).
     */
    public function store(StoreLeadRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $lead = Lead::create([
            'title'           => $validated['title'],
            'stage'           => LeadStage::NEW_LEAD,
            'status'          => LeadStatus::ACTIVE,
            'source'          => $validated['source'] ?? null,
            'expected_budget' => $validated['expected_budget'] ?? null,
            'notes'           => $validated['notes'] ?? null,
            'assigned_to'     => $request->user()?->id,
            // Store contact details in metadata until the lead is converted
            'metadata'        => array_filter([
                'company_name'  => $validated['company_name'] ?? null,
                'contact_name'  => $validated['contact_name'] ?? null,
                'primary_email' => $validated['primary_email'] ?? null,
                'primary_phone' => $validated['primary_phone'] ?? null,
            ], fn ($v) => $v !== null),
        ]);

        return ApiResponse::success(
            [
                'id'              => $lead->id,
                'title'           => $lead->title,
                'stage'           => $lead->stage->value,
                'status'          => $lead->status->value,
                'company'         => $lead->metadata['company_name'] ?? null,
                'email'           => $lead->metadata['primary_email'] ?? null,
                'phone'           => $lead->metadata['primary_phone'] ?? null,
                'expected_budget' => $lead->expected_budget,
                'source'          => $lead->source,
                'assignedTo'      => $lead->assigned_to,
                'createdAt'       => $lead->created_at?->toISOString(),
                'updatedAt'       => $lead->updated_at?->toISOString(),
            ],
            'Lead created successfully.',
            201
        );
    }

    private function hasCompletedMeeting(Lead $lead): bool
    {
        return Meeting::query()
            ->where('model_type', Lead::class)
            ->where('model_id', $lead->id)
            ->where('status', 'completed')
            ->exists();
    }

    private function hasAcceptedProposalOrQuotation(Lead $lead): bool
    {
        return $lead->proposals()->where('status', 'accepted')->exists()
            || $lead->quotations()->where('status', 'accepted')->exists();
    }
}
