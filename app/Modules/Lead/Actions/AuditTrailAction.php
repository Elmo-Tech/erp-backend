<?php

namespace App\Modules\Lead\Actions;

use App\Enums\AuditActionType;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadHistory;
use App\Modules\Lead\Models\LeadActivity;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AuditTrailAction
{
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

    public function getLeadFullHistory(Lead $lead, int $perPage = 50): LengthAwarePaginator
    {
        $proposalIds = $lead->proposals()->pluck('id')->toArray();
        $quotationIds = $lead->quotations()->pluck('id')->toArray();

        return LeadHistory::query()
            ->where(function ($query) use ($lead, $proposalIds, $quotationIds) {
                $query->where(function ($q) use ($lead) {
                    $q->where('auditable_type', Lead::class)
                        ->where('auditable_id', $lead->id);
                });

                if (! empty($proposalIds)) {
                    $query->orWhere(function ($q) use ($proposalIds) {
                        $q->where('auditable_type', \App\Models\Proposal::class)
                            ->whereIn('auditable_id', $proposalIds);
                    });
                }

                if (! empty($quotationIds)) {
                    $query->orWhere(function ($q) use ($quotationIds) {
                        $q->where('auditable_type', \App\Models\Quotation::class)
                            ->whereIn('auditable_id', $quotationIds);
                    });
                }
            })
            ->with('changedByUser')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

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

    public function getRecentActivity(int $limit = 10): Collection
    {
        return LeadHistory::query()
            ->with(['changedByUser', 'auditable'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function getUserActivity(int $userId, int $perPage = 20): LengthAwarePaginator
    {
        return LeadHistory::query()
            ->where('changed_by', $userId)
            ->with('changedByUser')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
