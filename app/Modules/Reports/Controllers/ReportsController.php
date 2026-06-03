<?php

namespace App\Modules\Reports\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Reports\Services\ReportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function __construct(
        private readonly ReportsService $reports,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $this->authorizeReports($request);

        return ApiResponse::success(
            $this->reports->overview($request),
            'Overview report retrieved successfully'
        );
    }

    public function projects(Request $request): JsonResponse
    {
        $this->authorizeReports($request);

        return ApiResponse::success(
            $this->reports->projects($request),
            'Project profitability report retrieved successfully'
        );
    }

    public function teamTime(Request $request): JsonResponse
    {
        $this->authorizeReports($request);

        return ApiResponse::success(
            $this->reports->teamTime($request),
            'Team time report retrieved successfully'
        );
    }

    public function salesFinance(Request $request): JsonResponse
    {
        $this->authorizeReports($request);

        return ApiResponse::success(
            $this->reports->salesFinance($request),
            'Sales finance report retrieved successfully'
        );
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $this->authorizeReports($request);

        $report = (string) $request->query('report', 'overview');

        if (! in_array($report, ['overview', 'projects', 'team-time', 'sales-finance'], true)) {
            return ApiResponse::validationError(['report' => ['Invalid report selected.']]);
        }

        [$filename, $headers, $rows] = $this->reports->csv($request, $report);

        return response()->streamDownload(function () use ($headers, $rows): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);

            foreach ($rows as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function authorizeReports(Request $request): void
    {
        abort_if(! $request->user()?->can('view_reports'), 403, 'Unauthorized');
    }
}
