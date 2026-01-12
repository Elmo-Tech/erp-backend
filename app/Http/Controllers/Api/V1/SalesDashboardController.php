<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Sales\SalesDashboardService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Sales Dashboard",
 *     description="Manager dashboard and alerts endpoints"
 * )
 */
class SalesDashboardController extends Controller
{
    public function __construct(
        private SalesDashboardService $dashboardService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/sales-dashboard/summary",
     *     operationId="getSalesDashboardSummary",
     *     tags={"Sales Dashboard"},
     *     summary="Get sales dashboard summary",
     *     description="Get pipeline health, alerts, and today's schedule",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Response(response=200, description="Dashboard summary retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function summary(): JsonResponse
    {
        $data = $this->dashboardService->getDashboardSummary();

        return ApiResponse::success($data, __('dashboard.summary_retrieved'));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/sales-dashboard/pipeline",
     *     operationId="getPipelineHealth",
     *     tags={"Sales Dashboard"},
     *     summary="Get pipeline health metrics",
     *     description="Get leads, proposals, and quotations by status with conversion rates",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Response(response=200, description="Pipeline health retrieved successfully")
     * )
     */
    public function pipeline(): JsonResponse
    {
        $data = $this->dashboardService->getPipelineHealth();

        return ApiResponse::success($data, __('dashboard.pipeline_retrieved'));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/sales-dashboard/team-performance",
     *     operationId="getTeamPerformance",
     *     tags={"Sales Dashboard"},
     *     summary="Get team performance metrics",
     *     description="Get sales reps and tech leads performance with win rates",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="startDate", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="endDate", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Team performance retrieved successfully")
     * )
     */
    public function teamPerformance(Request $request): JsonResponse
    {
        $startDate = $request->input('startDate') ? Carbon::parse($request->input('startDate')) : null;
        $endDate = $request->input('endDate') ? Carbon::parse($request->input('endDate')) : null;

        $data = $this->dashboardService->getTeamPerformance($startDate, $endDate);

        return ApiResponse::success($data, __('dashboard.team_performance_retrieved'));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/sales-dashboard/bottlenecks",
     *     operationId="getBottleneckAnalysis",
     *     tags={"Sales Dashboard"},
     *     summary="Get bottleneck analysis",
     *     description="Analyze where leads are getting stuck and loss reasons",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Response(response=200, description="Bottleneck analysis retrieved successfully")
     * )
     */
    public function bottlenecks(): JsonResponse
    {
        $data = $this->dashboardService->getBottleneckAnalysis();

        return ApiResponse::success($data, __('dashboard.bottlenecks_retrieved'));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/sales-dashboard/alerts",
     *     operationId="getSalesAlerts",
     *     tags={"Sales Dashboard"},
     *     summary="Get sales alerts",
     *     description="Get expiring proposals/quotations, overdue activities, and other alerts",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Response(response=200, description="Alerts retrieved successfully")
     * )
     */
    public function alerts(): JsonResponse
    {
        $data = $this->dashboardService->getAlerts();

        return ApiResponse::success($data, __('dashboard.alerts_retrieved'));
    }
}
