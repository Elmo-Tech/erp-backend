<?php

namespace App\Modules\Report\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Report\Services\SalesAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class SalesAnalyticsController extends Controller
{
    public function __construct(
        private SalesAnalyticsService $analyticsService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analytics/sales/performance",
     *     operationId="getSalesPerformance",
     *     tags={"Sales Analytics"},
     *     summary="Get sales performance by user",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="startDate", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="endDate", in="query", @OA\Schema(type="string", format="date")),
     *
     *     @OA\Response(response=200, description="Performance data retrieved successfully")
     * )
     */
    public function performance(Request $request): JsonResponse
    {
        $startDate = $request->input('startDate') ? Carbon::parse($request->input('startDate')) : null;
        $endDate = $request->input('endDate') ? Carbon::parse($request->input('endDate')) : null;

        $data = $this->analyticsService->getSalesPerformance($startDate, $endDate);

        return ApiResponse::success([
            'period' => [
                'startDate' => ($startDate ?? now()->startOfMonth())->format('Y-m-d'),
                'endDate' => ($endDate ?? now()->endOfMonth())->format('Y-m-d'),
            ],
            'salesReps' => $data,
        ], __('analytics.performance_retrieved'));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analytics/sales/sources",
     *     operationId="getSourceAnalytics",
     *     tags={"Sales Analytics"},
     *     summary="Get leads by source analytics",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Source analytics retrieved successfully")
     * )
     */
    public function sources(Request $request): JsonResponse
    {
        $startDate = $request->input('startDate') ? Carbon::parse($request->input('startDate')) : null;
        $endDate = $request->input('endDate') ? Carbon::parse($request->input('endDate')) : null;

        $data = $this->analyticsService->getSourceAnalytics($startDate, $endDate);

        return ApiResponse::success([
            'period' => [
                'startDate' => ($startDate ?? now()->startOfMonth())->format('Y-m-d'),
                'endDate' => ($endDate ?? now()->endOfMonth())->format('Y-m-d'),
            ],
            'sources' => $data,
        ], __('analytics.sources_retrieved'));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analytics/sales/pipeline",
     *     operationId="getSalesPipeline",
     *     tags={"Sales Analytics"},
     *     summary="Get sales pipeline",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Pipeline data retrieved successfully")
     * )
     */
    public function pipeline(): JsonResponse
    {
        $data = $this->analyticsService->getPipeline();

        return ApiResponse::success([
            'stages' => $data,
        ], __('analytics.pipeline_retrieved'));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analytics/revenue/forecast",
     *     operationId="getRevenueForecast",
     *     tags={"Sales Analytics"},
     *     summary="Get revenue forecast based on milestones",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Forecast data retrieved successfully")
     * )
     */
    public function forecast(): JsonResponse
    {
        $data = $this->analyticsService->getRevenueForecast();

        return ApiResponse::success($data, __('analytics.forecast_retrieved'));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analytics/quotations/stats",
     *     operationId="getQuotationStats",
     *     tags={"Sales Analytics"},
     *     summary="Get quotation statistics",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Quotation stats retrieved successfully")
     * )
     */
    public function quotationStats(Request $request): JsonResponse
    {
        $startDate = $request->input('startDate') ? Carbon::parse($request->input('startDate')) : null;
        $endDate = $request->input('endDate') ? Carbon::parse($request->input('endDate')) : null;

        $data = $this->analyticsService->getQuotationStats($startDate, $endDate);

        return ApiResponse::success($data, __('analytics.quotation_stats_retrieved'));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analytics/projects/types",
     *     operationId="getProjectTypesBreakdown",
     *     tags={"Sales Analytics"},
     *     summary="Get project types breakdown",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Project types data retrieved successfully")
     * )
     */
    public function projectTypes(): JsonResponse
    {
        $data = $this->analyticsService->getProjectTypesBreakdown();

        return ApiResponse::success([
            'types' => $data,
        ], __('analytics.project_types_retrieved'));
    }
}
