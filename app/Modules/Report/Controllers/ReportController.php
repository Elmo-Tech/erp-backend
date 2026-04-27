<?php

namespace App\Modules\Report\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Report\Actions\GenerateAdminOverviewReportAction;
use App\Modules\Report\Actions\GenerateFinancialReportAction;
use App\Modules\Report\Actions\GenerateQualityReportAction;
use App\Modules\Report\Actions\GenerateUserPerformanceReportAction;
use App\Modules\Report\Actions\GenerateEmployeeReviewReportAction;
use App\Modules\Report\Actions\GenerateProjectProgressReportAction;
use App\Modules\Report\Actions\GenerateSalesReportAction;
use App\Services\Reporting\PdfService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Reports', description: 'Report generation and analytics endpoints')]
class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    public function adminOverview(GenerateAdminOverviewReportAction $action): JsonResponse
    {
        if (!auth()->user()->can('view_admin_stats')) {
            return ApiResponse::forbidden('You do not have permission to view admin statistics');
        }

        try {
            return ApiResponse::success($action->execute(), 'Admin overview statistics retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve admin overview statistics: '.$e->getMessage(), 500);
        }
    }

    public function profitability(Request $request, GenerateFinancialReportAction $action): JsonResponse
    {
        if (!auth()->user()->can('view_admin_stats') || !auth()->user()->can('view_internal_costs')) {
            return ApiResponse::forbidden('You do not have permission to view profitability data');
        }

        try {
            $filters = $request->only(['status', 'dateFrom', 'dateTo']);
            $sortBy = $request->get('sortBy', 'profit');
            $sortDirection = $request->get('sortDirection', 'desc');
            $perPage = (int) $request->get('perPage', 15);

            $report = $action->execute($filters, $sortBy, $sortDirection, $perPage);

            return ApiResponse::success([
                'projects' => $report->items(),
                'pagination' => [
                    'total' => $report->total(),
                    'count' => $report->count(),
                    'perPage' => $report->perPage(),
                    'currentPage' => $report->currentPage(),
                    'totalPages' => $report->lastPage(),
                ],
            ], 'Profitability report retrieved successfully');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::validationError(['sortBy' => [$e->getMessage()]], 'Invalid filter parameters');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve profitability report: '.$e->getMessage(), 500);
        }
    }

    public function quality(Request $request, GenerateQualityReportAction $action): JsonResponse
    {
        if (!auth()->user()->can('view_admin_stats')) {
            return ApiResponse::forbidden('You do not have permission to view admin statistics');
        }

        try {
            $filters = $request->only(['dateFrom', 'dateTo']);
            $limit = (int) $request->get('limit', 10);

            return ApiResponse::success($action->execute($filters, $limit), 'Quality metrics retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve quality metrics: '.$e->getMessage(), 500);
        }
    }

    public function userPerformance(Request $request, GenerateUserPerformanceReportAction $action): JsonResponse
    {
        if (!auth()->user()->can('view_admin_stats')) {
            return ApiResponse::forbidden('You do not have permission to view admin statistics');
        }

        try {
            $filters = $request->only(['dateFrom', 'dateTo']);

            return ApiResponse::success($action->execute($filters), 'User performance metrics retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve user performance metrics: '.$e->getMessage(), 500);
        }
    }

    public function attendance(Request $request, GenerateUserPerformanceReportAction $action): JsonResponse
    {
        if (!auth()->user()->can('view_admin_stats')) {
            return ApiResponse::forbidden('You do not have permission to view admin statistics');
        }

        try {
            $filters = $request->only(['dateFrom', 'dateTo']);
            $data = $action->execute($filters);

            return ApiResponse::success($data, 'Attendance analytics retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve attendance analytics: '.$e->getMessage(), 500);
        }
    }

    public function employeeReview(Request $request, GenerateEmployeeReviewReportAction $action, int $userId): JsonResponse
    {
        if (!auth()->user()->can('view_admin_stats')) {
            return ApiResponse::forbidden('You do not have permission to view admin statistics');
        }

        try {
            $filters = $request->only(['dateFrom', 'dateTo', 'page', 'perPage']);

            return ApiResponse::success($action->execute($userId, $filters), 'Employee review retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('User not found', 404);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to retrieve employee review: '.$e->getMessage(), 500);
        }
    }

    public function exportEmployeeReview(Request $request, GenerateEmployeeReviewReportAction $action, PdfService $pdfService, int $userId)
    {
        if (!$request->user()->can('view_admin_stats')) {
            return ApiResponse::error('Access denied. You do not have permission to view admin statistics.', 403);
        }

        try {
            $user = \App\Modules\User\Models\User::findOrFail($userId);
            $filters = $request->only(['dateFrom', 'dateTo', 'page', 'perPage']);

            $reviewData = $action->execute($userId, $filters);

            $locale = $request->header('Accept-Language', 'en');
            if (!in_array($locale, ['ar', 'en'])) {
                $locale = 'en';
            }
            app()->setLocale($locale);
            $reviewData['locale'] = $locale;

            $filters['forPdf'] = true;
            $completeData = $action->execute($userId, $filters);
            $reviewData['points'] = $completeData['points'];

            $dateRange = '';
            if (isset($filters['dateFrom']) && isset($filters['dateTo'])) {
                $dateRange = '_'.$filters['dateFrom'].'_to_'.$filters['dateTo'];
            }
            $fileName = 'Employee Review'.str_replace(' ', ' ', $user->name).$dateRange.'.pdf';

            return $pdfService->downloadPdf('reports.employee-review', $reviewData, $fileName, [
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'enableFontSubsetting' => false,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('User not found', 404);
        } catch (\Throwable $e) {
            \Log::error('Employee Review Export Error', [
                'userId' => $userId,
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('Failed to export employee review: '.$e->getMessage(), 500);
        }
    }

    public function projectProgress(Request $request, GenerateProjectProgressReportAction $action): JsonResponse
    {
        try {
            $status = $request->get('status');
            $dateFrom = $request->get('dateFrom');
            $dateTo = $request->get('dateTo');

            return ApiResponse::success($action->execute($status, $dateFrom, $dateTo), 'Project progress report retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve project progress report: '.$e->getMessage(), 500);
        }
    }

    public function salesReport(Request $request, GenerateSalesReportAction $action): JsonResponse
    {
        $request->validate([
            'startDate' => 'required|date',
            'endDate' => 'required|date|after_or_equal:startDate',
        ]);

        $startDate = Carbon::parse($request->input('startDate'))->startOfDay();
        $endDate = Carbon::parse($request->input('endDate'))->endOfDay();

        return ApiResponse::success($action->execute($startDate, $endDate), 'Sales report generated successfully');
    }
}
