<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Audit\LeadHistoryCollection;
use App\Http\Resources\V1\Audit\LeadHistoryResource;
use App\Models\Lead;
use App\Models\Proposal;
use App\Models\Quotation;
use App\Services\Sales\AuditTrailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Audit Trail",
 *     description="Audit trail and history endpoints for leads, proposals, and quotations"
 * )
 */
class LeadHistoryController extends Controller
{
    public function __construct(
        private AuditTrailService $auditService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/leads/{id}/history",
     *     operationId="getLeadHistory",
     *     tags={"Audit Trail"},
     *     summary="Get audit history for a lead",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="filter[actionType]", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="filter[fieldName]", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="perPage", in="query", @OA\Schema(type="integer", default=20)),
     *
     *     @OA\Response(response=200, description="History retrieved successfully"),
     *     @OA\Response(response=404, description="Lead not found")
     * )
     */
    public function leadHistory(Request $request, Lead $lead): JsonResponse
    {
        $history = $this->auditService->getEntityHistory(
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

    /**
     * @OA\Get(
     *     path="/api/v1/leads/{id}/full-history",
     *     operationId="getLeadFullHistory",
     *     tags={"Audit Trail"},
     *     summary="Get full audit history for a lead including proposals and quotations",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="perPage", in="query", @OA\Schema(type="integer", default=50)),
     *
     *     @OA\Response(response=200, description="Full history retrieved successfully"),
     *     @OA\Response(response=404, description="Lead not found")
     * )
     */
    public function leadFullHistory(Request $request, Lead $lead): JsonResponse
    {
        $history = $this->auditService->getLeadFullHistory(
            $lead,
            $request->input('perPage', 50)
        );

        return ApiResponse::success(
            new LeadHistoryCollection($history),
            __('audit.full_history_retrieved')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/leads/{id}/history-stats",
     *     operationId="getLeadHistoryStats",
     *     tags={"Audit Trail"},
     *     summary="Get audit statistics for a lead",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Stats retrieved successfully")
     * )
     */
    public function leadHistoryStats(Lead $lead): JsonResponse
    {
        $stats = $this->auditService->getEntityStats($lead);

        return ApiResponse::success($stats, __('audit.stats_retrieved'));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/proposals/{id}/history",
     *     operationId="getProposalHistory",
     *     tags={"Audit Trail"},
     *     summary="Get audit history for a proposal",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="perPage", in="query", @OA\Schema(type="integer", default=20)),
     *
     *     @OA\Response(response=200, description="History retrieved successfully")
     * )
     */
    public function proposalHistory(Request $request, Proposal $proposal): JsonResponse
    {
        $history = $this->auditService->getEntityHistory(
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

    /**
     * @OA\Get(
     *     path="/api/v1/quotations/{id}/history",
     *     operationId="getQuotationHistory",
     *     tags={"Audit Trail"},
     *     summary="Get audit history for a quotation",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="perPage", in="query", @OA\Schema(type="integer", default=20)),
     *
     *     @OA\Response(response=200, description="History retrieved successfully")
     * )
     */
    public function quotationHistory(Request $request, Quotation $quotation): JsonResponse
    {
        $history = $this->auditService->getEntityHistory(
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

    /**
     * @OA\Get(
     *     path="/api/v1/audit/recent",
     *     operationId="getRecentAuditActivity",
     *     tags={"Audit Trail"},
     *     summary="Get recent audit activity across all entities",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=10)),
     *
     *     @OA\Response(response=200, description="Recent activity retrieved successfully")
     * )
     */
    public function recentActivity(Request $request): JsonResponse
    {
        $activity = $this->auditService->getRecentActivity(
            $request->input('limit', 10)
        );

        return ApiResponse::success(
            LeadHistoryResource::collection($activity),
            __('audit.recent_activity_retrieved')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/audit/user/{userId}",
     *     operationId="getUserAuditActivity",
     *     tags={"Audit Trail"},
     *     summary="Get audit activity by a specific user",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="perPage", in="query", @OA\Schema(type="integer", default=20)),
     *
     *     @OA\Response(response=200, description="User activity retrieved successfully")
     * )
     */
    public function userActivity(Request $request, int $userId): JsonResponse
    {
        $activity = $this->auditService->getUserActivity(
            $userId,
            $request->input('perPage', 20)
        );

        return ApiResponse::success(
            new LeadHistoryCollection($activity),
            __('audit.user_activity_retrieved')
        );
    }
}
