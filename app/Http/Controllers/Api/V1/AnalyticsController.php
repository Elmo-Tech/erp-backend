<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Analytics",
 *     description="Executive analytics and forecasting endpoints (Admin/Manager only)"
 * )
 */
class AnalyticsController extends Controller
{
    public function __construct(
        private AnalyticsService $analyticsService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
        // Security: Only admin and manager roles can access analytics
        $this->middleware('role:admin|manager');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analytics/sales-performance",
     *     summary="Get sales performance metrics and conversion rates",
     *     tags={"Analytics"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sales performance metrics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sales performance metrics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="overallMetrics",
     *                     type="object",
     *                     @OA\Property(property="totalLeads", type="integer", example=150),
     *                     @OA\Property(property="convertedLeads", type="integer", example=45),
     *                     @OA\Property(property="conversionRate", type="number", format="float", example=30.0),
     *                     @OA\Property(property="averageDealSize", type="number", format="float", example=25000.00),
     *                     @OA\Property(property="salesVelocity", type="number", format="float", example=21.5)
     *                 ),
     *                 @OA\Property(
     *                     property="conversionByRep",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe"),
     *                         @OA\Property(property="totalLeads", type="integer", example=25),
     *                         @OA\Property(property="wonLeads", type="integer", example=8),
     *                         @OA\Property(property="conversionRate", type="number", format="float", example=32.0)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pipelineHealth",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="status", type="string", example="negotiation"),
     *                         @OA\Property(property="statusLabel", type="string", example="Negotiation"),
     *                         @OA\Property(property="count", type="integer", example=12)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="monthlyTrend",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="month", type="string", example="2026-01"),
     *                         @OA\Property(property="totalLeads", type="integer", example=15),
     *                         @OA\Property(property="wonLeads", type="integer", example=5),
     *                         @OA\Property(property="conversionRate", type="number", format="float", example=33.33)
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function salesPerformance(): JsonResponse
    {
        $data = $this->analyticsService->getSalesPerformance();

        return ApiResponse::success($data, __('messages.sales_performance_retrieved'));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analytics/rejection-reasons",
     *     summary="Get rejection analysis and reasons why deals are lost",
     *     tags={"Analytics"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Rejection analysis retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Rejection analysis retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="rejectionReasons",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="reason", type="string", example="price"),
     *                         @OA\Property(property="reasonLabel", type="string", example="Price too high"),
     *                         @OA\Property(property="count", type="integer", example=15)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="rejectionBySector",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="sector", type="string", example="fintech"),
     *                         @OA\Property(property="sectorLabel", type="string", example="Fintech"),
     *                         @OA\Property(property="totalQuotations", type="integer", example=20),
     *                         @OA\Property(property="rejectedCount", type="integer", example=5),
     *                         @OA\Property(property="rejectionRate", type="number", format="float", example=25.0)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="monthlyTrend",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="month", type="string", example="2026-01"),
     *                         @OA\Property(property="totalQuotations", type="integer", example=25),
     *                         @OA\Property(property="rejectedQuotations", type="integer", example=6),
     *                         @OA\Property(property="rejectionRate", type="number", format="float", example=24.0)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="summary",
     *                     type="object",
     *                     @OA\Property(property="totalRejectedQuotations", type="integer", example=45),
     *                     @OA\Property(property="totalLostLeads", type="integer", example=30),
     *                     @OA\Property(property="overallRejectionRate", type="number", format="float", example=22.5)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function rejectionReasons(): JsonResponse
    {
        $data = $this->analyticsService->getRejectionAnalysis();

        return ApiResponse::success($data, __('messages.rejection_analysis_retrieved'));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analytics/financial-forecast",
     *     summary="Get cashflow forecast including receivables and projected revenue",
     *     tags={"Analytics"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Financial forecast retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Financial forecast retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="currentFinancials",
     *                     type="object",
     *                     @OA\Property(property="receivables", type="number", format="float", example=125000.00),
     *                     @OA\Property(property="overdueReceivables", type="number", format="float", example=25000.00),
     *                     @OA\Property(property="projectedRevenue", type="number", format="float", example=350000.00),
     *                     @OA\Property(property="totalPipeline", type="number", format="float", example=475000.00)
     *                 ),
     *                 @OA\Property(
     *                     property="revenueBySector",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="sector", type="string", example="fintech"),
     *                         @OA\Property(property="sectorLabel", type="string", example="Fintech"),
     *                         @OA\Property(property="totalRevenue", type="number", format="float", example=150000.00),
     *                         @OA\Property(property="contractCount", type="integer", example=5),
     *                         @OA\Property(property="averageContractValue", type="number", format="float", example=30000.00)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="monthlyCashflow",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="month", type="string", example="2026-03"),
     *                         @OA\Property(property="expectedAmount", type="number", format="float", example=45000.00)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="summary",
     *                     type="object",
     *                     @OA\Property(property="totalContracts", type="integer", example=25),
     *                     @OA\Property(property="activeContracts", type="integer", example=18),
     *                     @OA\Property(property="totalInvoices", type="integer", example=75),
     *                     @OA\Property(property="paidInvoices", type="integer", example=60)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function financialForecast(): JsonResponse
    {
        $data = $this->analyticsService->getCashflowForecast();

        return ApiResponse::success($data, __('messages.financial_forecast_retrieved'));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analytics/follow-up-radar",
     *     summary="Get items needing immediate attention (expiring quotations, stale leads, etc.)",
     *     tags={"Analytics"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Follow-up radar retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Follow-up radar retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="items",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="type", type="string", example="expiring_quotation"),
     *                         @OA\Property(property="id", type="integer", example=15),
     *                         @OA\Property(property="title", type="string", example="Quotation QT-2026-0015"),
     *                         @OA\Property(property="description", type="string", example="For ABC Corp - Expires Feb 5, 2026"),
     *                         @OA\Property(property="urgency", type="string", example="critical"),
     *                         @OA\Property(property="daysLeft", type="integer", example=1),
     *                         @OA\Property(property="amount", type="number", format="float", example=25000.00),
     *                         @OA\Property(property="leadName", type="string", example="ABC Corp")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="summary",
     *                     type="object",
     *                     @OA\Property(property="totalItems", type="integer", example=12),
     *                     @OA\Property(property="criticalItems", type="integer", example=3),
     *                     @OA\Property(property="highPriorityItems", type="integer", example=5),
     *                     @OA\Property(property="mediumPriorityItems", type="integer", example=4)
     *                 ),
     *                 @OA\Property(
     *                     property="breakdown",
     *                     type="object",
     *                     @OA\Property(property="expiringQuotations", type="integer", example=2),
     *                     @OA\Property(property="staleLeads", type="integer", example=4),
     *                     @OA\Property(property="overdueInvoices", type="integer", example=3),
     *                     @OA\Property(property="followUpNeeded", type="integer", example=3)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function followUpRadar(): JsonResponse
    {
        $data = $this->analyticsService->getFollowUpRadar();

        return ApiResponse::success($data, __('messages.follow_up_radar_retrieved'));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analytics/dashboard-summary",
     *     summary="Get executive dashboard summary with key metrics",
     *     tags={"Analytics"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Dashboard summary retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="salesMetrics",
     *                     type="object",
     *                     @OA\Property(property="totalLeads", type="integer", example=150),
     *                     @OA\Property(property="conversionRate", type="number", format="float", example=30.0),
     *                     @OA\Property(property="averageDealSize", type="number", format="float", example=25000.00)
     *                 ),
     *                 @OA\Property(
     *                     property="financialMetrics",
     *                     type="object",
     *                     @OA\Property(property="receivables", type="number", format="float", example=125000.00),
     *                     @OA\Property(property="projectedRevenue", type="number", format="float", example=350000.00),
     *                     @OA\Property(property="totalPipeline", type="number", format="float", example=475000.00)
     *                 ),
     *                 @OA\Property(
     *                     property="urgentItems",
     *                     type="object",
     *                     @OA\Property(property="totalItems", type="integer", example=12),
     *                     @OA\Property(property="criticalItems", type="integer", example=3)
     *                 ),
     *                 @OA\Property(
     *                     property="rejectionRate",
     *                     type="number",
     *                     format="float",
     *                     example=22.5
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function dashboardSummary(): JsonResponse
    {
        // Get key metrics from all analytics methods
        $salesData = $this->analyticsService->getSalesPerformance();
        $financialData = $this->analyticsService->getCashflowForecast();
        $followUpData = $this->analyticsService->getFollowUpRadar();
        $rejectionData = $this->analyticsService->getRejectionAnalysis();

        $summary = [
            'salesMetrics' => [
                'totalLeads' => $salesData['overallMetrics']['totalLeads'],
                'conversionRate' => $salesData['overallMetrics']['conversionRate'],
                'averageDealSize' => $salesData['overallMetrics']['averageDealSize'],
                'salesVelocity' => $salesData['overallMetrics']['salesVelocity'],
            ],
            'financialMetrics' => [
                'receivables' => $financialData['currentFinancials']['receivables'],
                'projectedRevenue' => $financialData['currentFinancials']['projectedRevenue'],
                'totalPipeline' => $financialData['currentFinancials']['totalPipeline'],
                'overdueReceivables' => $financialData['currentFinancials']['overdueReceivables'],
            ],
            'urgentItems' => [
                'totalItems' => $followUpData['summary']['totalItems'],
                'criticalItems' => $followUpData['summary']['criticalItems'],
                'highPriorityItems' => $followUpData['summary']['highPriorityItems'],
            ],
            'rejectionRate' => $rejectionData['summary']['overallRejectionRate'],
        ];

        return ApiResponse::success($summary, __('messages.dashboard_summary_retrieved'));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/analytics/clear-cache",
     *     summary="Clear analytics cache (Admin only)",
     *     tags={"Analytics"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Analytics cache cleared successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Analytics cache cleared successfully")
     *         )
     *     )
     * )
     */
    public function clearCache(): JsonResponse
    {
        $this->analyticsService->clearCache();

        return ApiResponse::success(null, __('messages.analytics_cache_cleared'));
    }
}