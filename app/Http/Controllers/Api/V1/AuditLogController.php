<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AuditLog\AuditLogCollection;
use App\Http\Resources\V1\AuditLog\AuditLogResource;
use App\Models\AuditLog;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @OA\Tag(
 *     name="Audit Logs",
 *     description="System audit trail endpoints (Admin only)"
 * )
 */
class AuditLogController extends Controller
{
    public function __construct(
        private AuditService $auditService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/audit-logs",
     *     summary="Get paginated list of audit logs (Admin only)",
     *     tags={"Audit Logs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *     @OA\Parameter(
     *         name="filter[event_type]",
     *         in="query",
     *         description="Filter by event type",
     *         @OA\Schema(type="string", enum={"contract", "invoice", "project", "payment", "security", "system"})
     *     ),
     *     @OA\Parameter(
     *         name="filter[severity]",
     *         in="query",
     *         description="Filter by severity level",
     *         @OA\Schema(type="string", enum={"info", "warning", "critical"})
     *     ),
     *     @OA\Parameter(
     *         name="filter[user_id]",
     *         in="query",
     *         description="Filter by user ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="filter[action]",
     *         in="query",
     *         description="Search in action field",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="filter[date_from]",
     *         in="query",
     *         description="Filter from date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="filter[date_to]",
     *         in="query",
     *         description="Filter to date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Audit logs retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Audit logs retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="auditLogs",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/AuditLogResource")
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer"),
     *                     @OA\Property(property="count", type="integer"),
     *                     @OA\Property(property="perPage", type="integer"),
     *                     @OA\Property(property="currentPage", type="integer"),
     *                     @OA\Property(property="totalPages", type="integer")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()?->can('view_audit_logs')) {
            return ApiResponse::forbidden('You do not have permission to view audit logs');
        }

        $auditLogs = QueryBuilder::for(AuditLog::class)
            ->with(['user', 'auditable'])
            ->allowedFilters([
                AllowedFilter::exact('event_type'),
                AllowedFilter::exact('severity'),
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('auditable_id'),
                AllowedFilter::partial('action'),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($searchQuery) use ($value) {
                        $searchQuery
                            ->where('action', 'like', "%{$value}%")
                            ->orWhere('ip_address', 'like', "%{$value}%")
                            ->orWhere('user_agent', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::callback('date_from', function ($query, $value) {
                    $query->whereDate('created_at', '>=', $value);
                }),
                AllowedFilter::callback('date_to', function ($query, $value) {
                    $query->whereDate('created_at', '<=', $value);
                }),
            ])
            ->defaultSort('-created_at')
            ->allowedSorts(['created_at', 'event_type', 'severity', 'action'])
            ->paginate($request->get('perPage', 20));

        return ApiResponse::success(
            new AuditLogCollection($auditLogs),
            'Audit logs retrieved successfully'
        );
    }

    public function actionTypes(Request $request): JsonResponse
    {
        if (!$request->user()?->can('view_audit_logs')) {
            return ApiResponse::forbidden('You do not have permission to view audit logs');
        }

        $actionTypes = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->values()
            ->toArray();

        return ApiResponse::success($actionTypes, 'Audit log action types retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/audit-logs/{id}",
     *     summary="Get audit log details (Admin only)",
     *     tags={"Audit Logs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Audit log retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Audit log retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/AuditLogResource")
     *         )
     *     )
     * )
     */
    public function show(AuditLog $auditLog): JsonResponse
    {
        if (!request()->user()?->can('view_audit_logs')) {
            return ApiResponse::forbidden('You do not have permission to view audit logs');
        }

        $auditLog->load(['user', 'auditable']);

        return ApiResponse::success(
            new AuditLogResource($auditLog),
            'Audit log retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/audit-logs/stats",
     *     summary="Get audit log statistics (Admin only)",
     *     tags={"Audit Logs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *     @OA\Parameter(
     *         name="days",
     *         in="query",
     *         description="Number of days to include in stats",
     *         @OA\Schema(type="integer", default=30)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Audit log statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Audit log statistics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="totalEvents", type="integer", example=1250),
     *                 @OA\Property(property="criticalEvents", type="integer", example=5),
     *                 @OA\Property(property="warningEvents", type="integer", example=45),
     *                 @OA\Property(property="infoEvents", type="integer", example=1200),
     *                 @OA\Property(
     *                     property="eventsByType",
     *                     type="object",
     *                     @OA\Property(property="contract", type="integer", example=150),
     *                     @OA\Property(property="invoice", type="integer", example=300),
     *                     @OA\Property(property="project", type="integer", example=200),
     *                     @OA\Property(property="payment", type="integer", example=100),
     *                     @OA\Property(property="security", type="integer", example=25),
     *                     @OA\Property(property="system", type="integer", example=475)
     *                 ),
     *                 @OA\Property(
     *                     property="dailyActivity",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="date", type="string", format="date"),
     *                         @OA\Property(property="count", type="integer")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function stats(Request $request): JsonResponse
    {
        if (!$request->user()?->can('view_audit_logs')) {
            return ApiResponse::forbidden('You do not have permission to view audit logs');
        }

        $days = $request->get('days', 30);
        $fromDate = now()->subDays($days);

        // Total events in period
        $totalEvents = AuditLog::where('created_at', '>=', $fromDate)->count();

        // Events by severity
        $severityStats = AuditLog::where('created_at', '>=', $fromDate)
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        // Events by type
        $typeStats = AuditLog::where('created_at', '>=', $fromDate)
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->pluck('count', 'event_type')
            ->toArray();

        // Daily activity
        $dailyActivity = AuditLog::where('created_at', '>=', $fromDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'count' => $item->count
                ];
            });

        $stats = [
            'totalEvents' => $totalEvents,
            'criticalEvents' => $severityStats['critical'] ?? 0,
            'warningEvents' => $severityStats['warning'] ?? 0,
            'infoEvents' => $severityStats['info'] ?? 0,
            'eventsByType' => $typeStats,
            'dailyActivity' => $dailyActivity,
        ];

        return ApiResponse::success($stats, __('messages.audit_stats_retrieved'));
    }
}
