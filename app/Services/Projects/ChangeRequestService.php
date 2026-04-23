<?php

namespace App\Services\Projects;

use App\Enums\ChangeRequestStatus;
use App\Models\ChangeRequest;
use App\Modules\Project\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ChangeRequestService
{
    /**
     * Get change requests for a project
     */
    public function getChangeRequests(Project $project, array $filters = []): LengthAwarePaginator
    {
        return QueryBuilder::for(ChangeRequest::class)
            ->where('project_id', $project->id)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('is_billable'),
                'search' => AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('cr_number', 'like', "%{$value}%")
                            ->orWhere('title', 'like', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts(['created_at', 'estimated_cost', 'status'])
            ->allowedIncludes(['requester', 'approver', 'project'])
            ->defaultSort('-created_at')
            ->paginate($filters['perPage'] ?? 15);
    }

    /**
     * Create a change request
     */
    public function createChangeRequest(Project $project, array $data, User $user): ChangeRequest
    {
        return ChangeRequest::create([
            'cr_number' => ChangeRequest::generateCRNumber($project->id),
            'project_id' => $project->id,
            'requested_by' => $user->id,
            'title' => $data['title'],
            'description' => $data['description'],
            'impact_analysis' => $data['impactAnalysis'] ?? null,
            'is_billable' => $data['isBillable'] ?? true,
            'estimated_hours' => $data['estimatedHours'] ?? 0,
            'estimated_cost' => $data['estimatedCost'] ?? 0,
            'additional_days' => $data['additionalDays'] ?? 0,
            'status' => ChangeRequestStatus::PENDING,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    /**
     * Update change request
     */
    public function updateChangeRequest(ChangeRequest $changeRequest, array $data, User $user): ChangeRequest
    {
        $changeRequest->update([
            'title' => $data['title'] ?? $changeRequest->title,
            'description' => $data['description'] ?? $changeRequest->description,
            'impact_analysis' => $data['impactAnalysis'] ?? $changeRequest->impact_analysis,
            'is_billable' => $data['isBillable'] ?? $changeRequest->is_billable,
            'estimated_hours' => $data['estimatedHours'] ?? $changeRequest->estimated_hours,
            'estimated_cost' => $data['estimatedCost'] ?? $changeRequest->estimated_cost,
            'additional_days' => $data['additionalDays'] ?? $changeRequest->additional_days,
            'updated_by' => $user->id,
        ]);

        return $changeRequest->fresh();
    }

    /**
     * Submit for review
     */
    public function submitForReview(ChangeRequest $changeRequest, User $user): ChangeRequest
    {
        $changeRequest->update([
            'status' => ChangeRequestStatus::UNDER_REVIEW,
            'updated_by' => $user->id,
        ]);

        return $changeRequest->fresh();
    }

    /**
     * Approve change request
     */
    public function approve(ChangeRequest $changeRequest, User $user, ?float $approvedCost = null): ChangeRequest
    {
        $changeRequest->update([
            'status' => ChangeRequestStatus::APPROVED,
            'approved_by' => $user->id,
            'approved_at' => now(),
            'approved_cost' => $approvedCost ?? $changeRequest->estimated_cost,
            'updated_by' => $user->id,
        ]);

        return $changeRequest->fresh();
    }

    /**
     * Reject change request
     */
    public function reject(ChangeRequest $changeRequest, User $user, string $reason): ChangeRequest
    {
        $changeRequest->update([
            'status' => ChangeRequestStatus::REJECTED,
            'approved_by' => $user->id,
            'approved_at' => now(),
            'rejection_reason' => $reason,
            'updated_by' => $user->id,
        ]);

        return $changeRequest->fresh();
    }

    /**
     * Mark as implemented
     */
    public function markImplemented(ChangeRequest $changeRequest, User $user): ChangeRequest
    {
        $changeRequest->update([
            'status' => ChangeRequestStatus::IMPLEMENTED,
            'updated_by' => $user->id,
        ]);

        return $changeRequest->fresh();
    }

    /**
     * Get project CR summary
     */
    public function getProjectCRSummary(Project $project): array
    {
        $changeRequests = ChangeRequest::where('project_id', $project->id)->get();

        return [
            'total' => $changeRequests->count(),
            'pending' => $changeRequests->where('status', ChangeRequestStatus::PENDING)->count(),
            'approved' => $changeRequests->where('status', ChangeRequestStatus::APPROVED)->count(),
            'implemented' => $changeRequests->where('status', ChangeRequestStatus::IMPLEMENTED)->count(),
            'rejected' => $changeRequests->where('status', ChangeRequestStatus::REJECTED)->count(),
            'totalBillableHours' => $changeRequests
                ->where('is_billable', true)
                ->whereIn('status', [ChangeRequestStatus::APPROVED, ChangeRequestStatus::IMPLEMENTED])
                ->sum('estimated_hours'),
            'totalBillableCost' => $changeRequests
                ->where('is_billable', true)
                ->whereIn('status', [ChangeRequestStatus::APPROVED, ChangeRequestStatus::IMPLEMENTED])
                ->sum('approved_cost'),
            'totalFreeHours' => $changeRequests
                ->where('is_billable', false)
                ->whereIn('status', [ChangeRequestStatus::APPROVED, ChangeRequestStatus::IMPLEMENTED])
                ->sum('estimated_hours'),
        ];
    }
}
