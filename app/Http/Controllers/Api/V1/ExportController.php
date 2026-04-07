<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Sales\ExportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Export",
 *     description="Data export endpoints"
 * )
 */
class ExportController extends Controller
{
    public function __construct(
        private ExportService $exportService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/export/leads",
     *     operationId="exportLeads",
     *     tags={"Export"},
     *     summary="Export leads data",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="format", in="query", @OA\Schema(type="string", enum={"json", "csv"}, default="json")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="startDate", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="endDate", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="source", in="query", @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Export data retrieved")
     * )
     */
    public function exportLeads(Request $request): JsonResponse|Response
    {
        $filters = $request->only(['status', 'startDate', 'endDate', 'source']);
        $format = $request->input('format', 'json');

        $data = $this->exportService->exportLeads($filters);

        if ($format === 'csv') {
            return $this->generateCsvResponse($data);
        }

        return ApiResponse::success($data, __('export.leads_exported'));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/export/proposals",
     *     operationId="exportProposals",
     *     tags={"Export"},
     *     summary="Export proposals data",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="format", in="query", @OA\Schema(type="string", enum={"json", "csv"}, default="json")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="startDate", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="endDate", in="query", @OA\Schema(type="string", format="date")),
     *
     *     @OA\Response(response=200, description="Export data retrieved")
     * )
     */
    public function exportProposals(Request $request): JsonResponse|Response
    {
        $filters = $request->only(['status', 'startDate', 'endDate']);
        $format = $request->input('format', 'json');

        $data = $this->exportService->exportProposals($filters);

        if ($format === 'csv') {
            return $this->generateCsvResponse($data);
        }

        return ApiResponse::success($data, __('export.proposals_exported'));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/export/quotations",
     *     operationId="exportQuotations",
     *     tags={"Export"},
     *     summary="Export quotations data",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="format", in="query", @OA\Schema(type="string", enum={"json", "csv"}, default="json")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="startDate", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="endDate", in="query", @OA\Schema(type="string", format="date")),
     *
     *     @OA\Response(response=200, description="Export data retrieved")
     * )
     */
    public function exportQuotations(Request $request): JsonResponse|Response
    {
        $filters = $request->only(['status', 'startDate', 'endDate']);
        $format = $request->input('format', 'json');

        $data = $this->exportService->exportQuotations($filters);

        if ($format === 'csv') {
            return $this->generateCsvResponse($data);
        }

        return ApiResponse::success($data, __('export.quotations_exported'));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/export/sales-report",
     *     operationId="exportSalesReport",
     *     tags={"Export"},
     *     summary="Generate sales report",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="startDate", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="endDate", in="query", required=true, @OA\Schema(type="string", format="date")),
     *
     *     @OA\Response(response=200, description="Report generated")
     * )
     */
    public function salesReport(Request $request): JsonResponse
    {
        $request->validate([
            'startDate' => 'required|date',
            'endDate' => 'required|date|after_or_equal:startDate',
        ]);

        $startDate = Carbon::parse($request->input('startDate'))->startOfDay();
        $endDate = Carbon::parse($request->input('endDate'))->endOfDay();

        $report = $this->exportService->generateSalesReport($startDate, $endDate);

        return ApiResponse::success($report, __('export.report_generated'));
    }

    /**
     * Generate CSV response
     */
    private function generateCsvResponse(array $data): Response
    {
        $csv = implode(',', $data['headers'])."\n";

        foreach ($data['data'] as $row) {
            $csv .= implode(',', array_map(fn ($cell) => '"'.str_replace('"', '""', $cell ?? '').'"', $row))."\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$data['filename'].'.csv"',
        ]);
    }
}
