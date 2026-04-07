<?php

namespace App\Services\Sales;

use App\Enums\AuditActionType;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\Proposal;
use App\Models\Quotation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AuditTrailService
{
    /**
     * Get audit history for a specific entity
     */
    public function getEntityHistory(
        Model $entity,
        ?string $actionType = null,
        ?string $fieldName = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = LeadHistory::query()
            ->where('auditable_type', get_class($entity))
            ->where('auditable_id', $entity->id)
            ->with('changedByUser')
            ->orderByDesc('created_at');

        if ($actionType) {
            $query->where('action_type', $actionType);
        }

        if ($fieldName) {
            $query->where('field_name', $fieldName);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get audit history for a lead and all related entities
     */
    public function getLeadFullHistory(Lead $lead, int $perPage = 50): LengthAwarePaginator
    {
        // Get IDs of related proposals and quotations
        $proposalIds = $lead->proposals()->pluck('id')->toArray();
        $quotationIds = $lead->quotations()->pluck('id')->toArray();

        return LeadHistory::query()
            ->where(function ($query) use ($lead, $proposalIds, $quotationIds) {
                // Lead history
                $query->where(function ($q) use ($lead) {
                    $q->where('auditable_type', Lead::class)
                        ->where('auditable_id', $lead->id);
                });

                // Proposal history
                if (! empty($proposalIds)) {
                    $query->orWhere(function ($q) use ($proposalIds) {
                        $q->where('auditable_type', Proposal::class)
                            ->whereIn('auditable_id', $proposalIds);
                    });
                }

                // Quotation history
                if (! empty($quotationIds)) {
                    $query->orWhere(function ($q) use ($quotationIds) {
                        $q->where('auditable_type', Quotation::class)
                            ->whereIn('auditable_id', $quotationIds);
                    });
                }
            })
            ->with('changedByUser')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get all audit history with filters
     */
    public function getAllHistory(array $filters = []): LengthAwarePaginator
    {
        return QueryBuilder::for(LeadHistory::class)
            ->with('changedByUser')
            ->allowedFilters([
                AllowedFilter::exact('action_type'),
                AllowedFilter::exact('field_name'),
                AllowedFilter::exact('changed_by'),
                AllowedFilter::exact('auditable_type'),
                AllowedFilter::exact('auditable_id'),
                AllowedFilter::scope('between_dates', 'betweenDates'),
                'search' => AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('description', 'like', "%{$value}%")
                            ->orWhere('old_value', 'like', "%{$value}%")
                            ->orWhere('new_value', 'like', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts(['created_at', 'action_type', 'field_name'])
            ->defaultSort('-created_at')
            ->paginate($filters['perPage'] ?? 20);
    }

    /**
     * Get history statistics for an entity
     */
    public function getEntityStats(Model $entity): array
    {
        $histories = LeadHistory::query()
            ->where('auditable_type', get_class($entity))
            ->where('auditable_id', $entity->id)
            ->get();

        return [
            'totalChanges' => $histories->count(),
            'statusChanges' => $histories->where('action_type', AuditActionType::STATUS_CHANGE)->count(),
            'dataEdits' => $histories->where('action_type', AuditActionType::DATA_EDIT)->count(),
            'uploads' => $histories->where('action_type', AuditActionType::UPLOAD)->count(),
            'lastChange' => $histories->first()?->created_at?->toISOString(),
            'uniqueEditors' => $histories->pluck('changed_by')->unique()->filter()->count(),
        ];
    }

    /**
     * Get recent activity across all entities
     */
    public function getRecentActivity(int $limit = 10): Collection
    {
        return LeadHistory::query()
            ->with(['changedByUser', 'auditable'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get activity by user
     */
    public function getUserActivity(int $userId, int $perPage = 20): LengthAwarePaginator
    {
        return LeadHistory::query()
            ->where('changed_by', $userId)
            ->with('changedByUser')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
