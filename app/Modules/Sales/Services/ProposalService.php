<?php

namespace App\Modules\Sales\Services;

use App\Enums\ProposalActionType;
use App\Enums\ProposalStatus;
use App\Enums\TaskCategory;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Modules\CRM\Models\Client;
use App\Modules\Proposal\Models\Proposal;
use App\Modules\Task\Models\Task;
use App\Modules\User\Models\User;
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
            ->with(['client', 'template', 'task'])
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('client_id'),
                AllowedFilter::exact('is_fast_track'),
                AllowedFilter::exact('is_skipped'),
                AllowedFilter::callback('clientId', function ($query, $value) {
                    $query->where('client_id', $value);
                }),
                AllowedFilter::callback('isFastTrack', function ($query, $value) {
                    $query->where('is_fast_track', filter_var($value, FILTER_VALIDATE_BOOLEAN));
                }),
                AllowedFilter::scope('expiring_in'),
                AllowedFilter::scope('active'),
                'search' => AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('title', 'like', "%{$value}%")
                            ->orWhere('proposal_number', 'like', "%{$value}%")
                            ->orWhere('file_number', 'like', "%{$value}%")
                            ->orWhereHas('client', function ($clientQuery) use ($value) {
                                $clientQuery->where('display_name', 'like', "%{$value}%")
                                    ->orWhere('company_name', 'like', "%{$value}%");
                            });
                    });
                }),
            ])
            ->allowedSorts(['created_at', 'expiry_date', 'submission_date'])
            ->allowedIncludes(['client', 'template', 'quotations', 'task'])
            ->defaultSort('-created_at')
            ->paginate($filters['perPage'] ?? 15);
    }

    /**
     * Create a new proposal with support for three paths:
     * 1. Skip path: Mark proposal as skipped with reason
     * 2. Schedule path: Create proposal with follow-up task
     * 3. Upload path: Create proposal with file upload
     * 4. Draft path: Create draft proposal without special actions
     */
    public function createProposal(array $data, User $user): Proposal
    {
        $actionType = $data['actionType'] ?? ProposalActionType::DRAFT->value;

        // Generate proposal number and file number
        $proposalNumber = Proposal::generateProposalNumber();
        $fileNumber = Proposal::generateFileNumber();

        // Calculate expiry_date from expiryDays if provided, otherwise use expiryDate or default to 10 days
        $expiryDate = null;
        if (! empty($data['expiryDays'])) {
            $expiryDate = now()->addDays($data['expiryDays']);
        } elseif (! empty($data['expiryDate'])) {
            $expiryDate = $data['expiryDate'];
        } else {
            // Default to 10 days for non-skipped proposals
            $expiryDate = now()->addDays(10);
        }

        // Skip path should not have expiry_date (or set to null)
        if ($actionType === ProposalActionType::SKIP->value) {
            $expiryDate = null;
        }

        $proposalData = [
            'proposal_number' => $proposalNumber,
            'file_number' => $fileNumber,
            'client_id' => $data['clientId'],
            'template_id' => $data['templateId'] ?? null,
            'title' => $data['title'],
            'content' => $data['content'] ?? null,
            'status' => ProposalStatus::DRAFT,
            'expiry_date' => $expiryDate,
            'internal_notes' => $data['internalNotes'] ?? null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];

        // Handle different action types
        switch ($actionType) {
            case ProposalActionType::SKIP->value:
                // Skip path: Mark proposal as skipped
                $proposalData['is_skipped'] = true;
                $proposalData['skip_reason'] = $data['skipReason'];
                $proposalData['status'] = ProposalStatus::REJECTED;
                break;

            case ProposalActionType::SCHEDULE->value:
                // Schedule path: Create proposal with follow-up task
                $proposalData['status'] = ProposalStatus::SENT;
                $proposalData['sent_at'] = now();
                $proposalData['submission_date'] = now();
                break;

            case ProposalActionType::UPLOAD->value:
                // Upload path: File will be uploaded separately
                $proposalData['status'] = ProposalStatus::DRAFT;
                break;

            case ProposalActionType::DRAFT->value:
            default:
                // Draft path: Just create as draft
                $proposalData['status'] = ProposalStatus::DRAFT;
                break;
        }

        $proposal = Proposal::create($proposalData);

        // Handle schedule path: Create follow-up task
        if ($actionType === ProposalActionType::SCHEDULE->value) {
            $task = $this->createFollowUpTask($proposal, $data, $user);
            $proposal->update(['task_id' => $task->id]);
            $proposal = $proposal->fresh();
        }

        // Handle upload path: Upload file if provided
        if ($actionType === ProposalActionType::UPLOAD->value && isset($data['file']) && $data['file'] instanceof UploadedFile) {
            $this->uploadFile($proposal, $data['file'], $user);
            $proposal = $proposal->fresh();
        }

        // Keep the unified CRM lifecycle in sync for active proposal work.
        if ($actionType !== ProposalActionType::SKIP->value) {
            $this->updateClientLifecycleForProposal($proposal->client_id);
        }

        return $proposal->load(['client', 'task']);
    }

    /**
     * Create a follow-up task for a proposal (schedule path)
     */
    private function createFollowUpTask(Proposal $proposal, array $data, User $user): Task
    {
        $taskData = [
            'project_id' => null, // No project yet, will be created upon proposal approval
            'assigned_to' => $data['taskAssignedTo'],
            'title' => $data['taskTitle'] ?? 'Follow up on proposal: '.$proposal->title,
            'description' => 'Follow up task for proposal: '.$proposal->proposal_number."\n\n".($proposal->content ?? ''),
            'status' => TaskStatus::TODO,
            'type' => TaskType::TASK,
            'priority' => TaskPriority::MEDIUM,
            'task_category' => TaskCategory::FOLLOWUP,
            'due_date' => $data['taskDueDate'],
            'start_date' => now(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];

        return Task::create($taskData);
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

        $path = $file->store('proposals/'.$proposal->client_id, 'public');

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
        if (! $proposal->viewed_at) {
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
            'file_number' => Proposal::generateFileNumber(),
            'client_id' => $data['clientId'],
            'title' => $data['title'] ?? 'Fast Track - '.($data['projectName'] ?? 'Project'),
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
            ->with('client')
            ->orderBy('expiry_date')
            ->paginate(15);
    }

    /**
     * Update client lifecycle for proposal activity.
     */
    private function updateClientLifecycleForProposal(int $clientId): void
    {
        $client = Client::find($clientId);
        if ($client && $client->lifecycle_stage === 'qualified') {
            $client->update(['lifecycle_stage' => 'proposal']);
        }
    }
}
