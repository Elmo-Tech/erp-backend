<?php

namespace App\Services\Projects;

use App\Enums\HandoverStatus;
use App\Modules\Project\Models\Project;
use App\Models\ProjectHandover;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class HandoverService
{
    /**
     * Get handovers with filters
     */
    public function getHandovers(array $filters = []): LengthAwarePaginator
    {
        return QueryBuilder::for(ProjectHandover::class)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('project_id'),
                AllowedFilter::exact('sales_rep_id'),
                AllowedFilter::exact('project_manager_id'),
            ])
            ->allowedSorts(['created_at', 'handover_date', 'completed_at'])
            ->allowedIncludes(['project', 'contract', 'quotation', 'salesRep', 'projectManager'])
            ->defaultSort('-created_at')
            ->paginate($filters['perPage'] ?? 15);
    }

    /**
     * Create handover for project
     */
    public function createHandover(Project $project, array $data, User $user): ProjectHandover
    {
        return ProjectHandover::create([
            'project_id' => $project->id,
            'contract_id' => $data['contractId'] ?? null,
            'quotation_id' => $data['quotationId'] ?? null,
            'sales_rep_id' => $data['salesRepId'],
            'project_manager_id' => $data['projectManagerId'],
            'handover_date' => $data['handoverDate'] ?? now(),
            'scope_confirmed' => $data['scopeConfirmed'] ?? false,
            'requirements_clear' => $data['requirementsClear'] ?? false,
            'timeline_agreed' => $data['timelineAgreed'] ?? false,
            'budget_confirmed' => $data['budgetConfirmed'] ?? false,
            'risks_identified' => $data['risksIdentified'] ?? false,
            'notes' => $data['notes'] ?? null,
            'attachments' => $data['attachments'] ?? [],
            'status' => HandoverStatus::PENDING,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    /**
     * Update handover checklist
     */
    public function updateChecklist(ProjectHandover $handover, array $data, User $user): ProjectHandover
    {
        $handover->update([
            'scope_confirmed' => $data['scopeConfirmed'] ?? $handover->scope_confirmed,
            'requirements_clear' => $data['requirementsClear'] ?? $handover->requirements_clear,
            'timeline_agreed' => $data['timelineAgreed'] ?? $handover->timeline_agreed,
            'budget_confirmed' => $data['budgetConfirmed'] ?? $handover->budget_confirmed,
            'risks_identified' => $data['risksIdentified'] ?? $handover->risks_identified,
            'notes' => $data['notes'] ?? $handover->notes,
            'attachments' => $data['attachments'] ?? $handover->attachments,
            'updated_by' => $user->id,
        ]);

        return $handover->fresh();
    }

    /**
     * Complete handover
     */
    public function completeHandover(ProjectHandover $handover, User $user): ProjectHandover
    {
        if (! $handover->isChecklistComplete()) {
            throw new \InvalidArgumentException(__('handover.checklist_incomplete'));
        }

        $handover->update([
            'status' => HandoverStatus::COMPLETED,
            'completed_at' => now(),
            'updated_by' => $user->id,
        ]);

        return $handover->fresh();
    }

    /**
     * Mark handover as disputed
     */
    public function disputeHandover(ProjectHandover $handover, string $reason, User $user): ProjectHandover
    {
        $handover->update([
            'status' => HandoverStatus::DISPUTED,
            'notes' => $handover->notes."\n\n[DISPUTE]: ".$reason,
            'updated_by' => $user->id,
        ]);

        return $handover->fresh();
    }

    /**
     * Get pending handovers
     */
    public function getPendingHandovers(): LengthAwarePaginator
    {
        return ProjectHandover::where('status', HandoverStatus::PENDING)
            ->with(['project', 'salesRep', 'projectManager'])
            ->orderBy('handover_date')
            ->paginate(10);
    }

    /**
     * Check if project has completed handover
     */
    public function hasCompletedHandover(Project $project): bool
    {
        return ProjectHandover::where('project_id', $project->id)
            ->where('status', HandoverStatus::COMPLETED)
            ->exists();
    }
}
