<?php

namespace App\Modules\Lead\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Lead\Actions\AuditTrailAction;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Resources\LeadHistoryCollection;
use App\Modules\Lead\Resources\LeadHistoryResource;
use App\Modules\Proposal\Models\Proposal;
use App\Modules\Quotation\Models\Quotation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadHistoryController extends Controller
{
    public function __construct(
        private AuditTrailAction $auditTrailAction
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    public function leadHistory(Request $request, Lead $lead): JsonResponse
    {
        $history = $this->auditTrailAction->getEntityHistory(
            $lead,
            $request->input('filter.actionType'),
            $request->input('filter.fieldName'),
            $request->input('perPage', 20)
        );

        return ApiResponse::success(
            new LeadHistoryCollection($history),
            __('audit.history_retrieved')
        );
    }

    public function leadFullHistory(Request $request, Lead $lead): JsonResponse
    {
        $history = $this->auditTrailAction->getLeadFullHistory(
            $lead,
            $request->input('perPage', 50)
        );

        return ApiResponse::success(
            new LeadHistoryCollection($history),
            __('audit.full_history_retrieved')
        );
    }

    public function leadHistoryStats(Lead $lead): JsonResponse
    {
        $stats = $this->auditTrailAction->getEntityStats($lead);

        return ApiResponse::success($stats, __('audit.stats_retrieved'));
    }

    public function proposalHistory(Request $request, Proposal $proposal): JsonResponse
    {
        $history = $this->auditTrailAction->getEntityHistory(
            $proposal,
            $request->input('filter.actionType'),
            $request->input('filter.fieldName'),
            $request->input('perPage', 20)
        );

        return ApiResponse::success(
            new LeadHistoryCollection($history),
            __('audit.history_retrieved')
        );
    }

    public function quotationHistory(Request $request, Quotation $quotation): JsonResponse
    {
        $history = $this->auditTrailAction->getEntityHistory(
            $quotation,
            $request->input('filter.actionType'),
            $request->input('filter.fieldName'),
            $request->input('perPage', 20)
        );

        return ApiResponse::success(
            new LeadHistoryCollection($history),
            __('audit.history_retrieved')
        );
    }

    public function recentActivity(Request $request): JsonResponse
    {
        $activity = $this->auditTrailAction->getRecentActivity(
            $request->input('limit', 10)
        );

        return ApiResponse::success(
            LeadHistoryResource::collection($activity),
            __('audit.recent_activity_retrieved')
        );
    }

    public function userActivity(Request $request, int $userId): JsonResponse
    {
        $activity = $this->auditTrailAction->getUserActivity(
            $userId,
            $request->input('perPage', 20)
        );

        return ApiResponse::success(
            new LeadHistoryCollection($activity),
            __('audit.user_activity_retrieved')
        );
    }
}
