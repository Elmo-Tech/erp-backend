<?php

namespace App\Services\Sales;

use App\Enums\LeadStatus;
use App\Enums\ProposalStatus;
use App\Models\Lead;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProposalService
{
    /**
     * Get paginated proposals with filters
     */
    public function getProposals(array $filters = []): LengthAwarePaginator
    {
        return QueryBuilder::for(Proposal::class)
            ->with(['lead', 'template'])
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('lead_id'),
                AllowedFilter::exact('is_fast_track'),
                AllowedFilter::scope('expiring_in'),
                AllowedFilter::scope('active'),
                'search' => AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('title', 'like', "%{$value}%")
                            ->orWhere('proposal_number', 'like', "%{$value}%")
                            ->orWhereHas('lead', function ($leadQuery) use ($value) {
                                $leadQuery->where('name', 'like', "%{$value}%")
                                    ->orWhere('company', 'like', "%{$value}%");
                            });
                    });
                }),
            ])
            ->allowedSorts(['created_at', 'expiry_date', 'submission_date'])
            ->allowedIncludes(['lead', 'template', 'quotations'])
            ->defaultSort('-created_at')
            ->paginate($filters['perPage'] ?? 15);
    }

    /**
     * Create a new proposal
     */
    public function createProposal(array $data, User $user): Proposal
    {
        $proposalData = [
            'proposal_number' => Proposal::generateProposalNumber(),
            'lead_id' => $data['leadId'],
            'template_id' => $data['templateId'] ?? null,
            'title' => $data['title'],
            'content' => $data['content'] ?? null,
            'status' => ProposalStatus::DRAFT,
            'expiry_date' => $data['expiryDate'] ?? now()->addDays(10),
            'internal_notes' => $data['internalNotes'] ?? null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];

        $proposal = Proposal::create($proposalData);

        // Update lead status to proposal
        $this->updateLeadStatusToProposal($proposal->lead_id);

        return $proposal;
    }

    /**
     * Update a proposal
     */
    public function updateProposal(Proposal $proposal, array $data, User $user): Proposal
    {
        $proposal->update([
            'title' => $data['title'] ?? $proposal->title,
            'content' => $data['content'] ?? $proposal->content,
            'template_id' => $data['templateId'] ?? $proposal->template_id,
            'expiry_date' => $data['expiryDate'] ?? $proposal->expiry_date,
            'internal_notes' => $data['internalNotes'] ?? $proposal->internal_notes,
            'updated_by' => $user->id,
        ]);

        return $proposal->fresh();
    }

    /**
     * Upload proposal file
     */
    public function uploadFile(Proposal $proposal, UploadedFile $file, User $user): Proposal
    {
        // Delete old file if exists
        if ($proposal->file_path) {
            Storage::disk('public')->delete($proposal->file_path);
        }

        $path = $file->store('proposals/' . $proposal->lead_id, 'public');

        $proposal->update([
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'updated_by' => $user->id,
        ]);

        return $proposal->fresh();
    }

    /**
     * Send proposal to client
     */
    public function sendProposal(Proposal $proposal, User $user): Proposal
    {
        $proposal->update([
            'status' => ProposalStatus::SENT,
            'sent_at' => now(),
            'submission_date' => now(),
            'updated_by' => $user->id,
        ]);

        // TODO: Send email notification to client

        return $proposal->fresh();
    }

    /**
     * Mark proposal as viewed
     */
    public function markAsViewed(Proposal $proposal): Proposal
    {
        if (!$proposal->viewed_at) {
            $proposal->update([
                'status' => ProposalStatus::VIEWED,
                'viewed_at' => now(),
            ]);
        }

        return $proposal->fresh();
    }

    /**
     * Approve proposal
     */
    public function approveProposal(Proposal $proposal, User $user): Proposal
    {
        $proposal->update([
            'status' => ProposalStatus::APPROVED,
            'updated_by' => $user->id,
        ]);

        return $proposal->fresh();
    }

    /**
     * Reject proposal
     */
    public function rejectProposal(Proposal $proposal, array $data, User $user): Proposal
    {
        $proposal->update([
            'status' => ProposalStatus::REJECTED,
            'rejection_reason' => $data['rejectionReason'] ?? null,
            'rejection_notes' => $data['rejectionNotes'] ?? null,
            'updated_by' => $user->id,
        ]);

        return $proposal->fresh();
    }

    /**
     * Create fast track proposal (skip to quotation)
     */
    public function createFastTrack(array $data, User $user): Proposal
    {
        $proposal = Proposal::create([
            'proposal_number' => Proposal::generateProposalNumber(),
            'lead_id' => $data['leadId'],
            'title' => $data['title'] ?? 'Fast Track - ' . ($data['projectName'] ?? 'Project'),
            'status' => ProposalStatus::APPROVED,
            'is_fast_track' => true,
            'fast_track_reason' => $data['fastTrackReason'],
            'submission_date' => now(),
            'expiry_date' => now()->addDays(10),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return $proposal;
    }

    /**
     * Get expiring proposals
     */
    public function getExpiringProposals(int $days = 3): LengthAwarePaginator
    {
        return Proposal::expiringIn($days)
            ->with('lead')
            ->orderBy('expiry_date')
            ->paginate(15);
    }

    /**
     * Update lead status to proposal
     */
    private function updateLeadStatusToProposal(int $leadId): void
    {
        $lead = Lead::find($leadId);
        if ($lead && $lead->status === LeadStatus::QUALIFIED) {
            $lead->update(['status' => LeadStatus::PROPOSAL]);
        }
    }
}
