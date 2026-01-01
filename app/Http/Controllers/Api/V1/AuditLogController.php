<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Annotations as OA;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AuditLogController extends Controller
{
    public function __construct()
    {
        // Apply middleware using Laravel 12 approach
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/audit-logs",
     *     operationId="getAuditLogs",
     *     tags={"Admin Audit Logs"},
     *     summary="Get audit logs with filtering and pagination",
     *     description="Retrieve audit logs with comprehensive filtering by action, user, date range, and resource ID search. Requires view_audit_logs permission.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="filter[action]",
     *         in="query",
     *         description="Filter by action type",
     *         @OA\Schema(
     *             type="string",
     *             enum={
     *                 "login", "logout", "create_project", "update_project", "delete_project",
     *                 "create_task", "update_task", "delete_task", "update_internal_cost",
     *                 "update_role_permissions", "create_role", "delete_role", "view_profitability",
     *                 "forbidden_access_attempt", "approve_ui", "pay_invoice"
     *             },
     *             example="update_internal_cost"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="filter[user_id]",
     *         in="query",
     *         description="Filter by user ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="filter[auditable_type]",
     *         in="query",
     *         description="Filter by resource type",
     *         @OA\Schema(
     *             type="string",
     *             enum={"App\\Models\\User", "App\\Models\\Project", "App\\Models\\Task", "App\\Models\\Role", "App\\Models\\Invoice"},
     *             example="App\\Models\\User"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="filter[date_from]",
     *         in="query",
     *         description="Filter logs from this date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date", example="2024-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="filter[date_to]",
     *         in="query",
     *         description="Filter logs up to this date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date", example="2024-12-31")
     *     ),
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         description="Search by resource ID or IP address",
     *         @OA\Schema(type="string", example="192.168.1.1")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort by field (prefix with - for descending)",
     *         @OA\Schema(type="string", enum={"created_at", "-created_at", "action", "-action", "user_id", "-user_id"}, example="-created_at")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, example=15)
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
     *                     @OA\Items(
     *                         @OA\Property(property="auditLogId", type="integer", example=1),
     *                         @OA\Property(property="userId", type="integer", example=1),
     *                         @OA\Property(property="userName", type="string", example="John Doe"),
     *                         @OA\Property(property="action", type="string", example="update_internal_cost"),
     *                         @OA\Property(property="auditableType", type="string", example="App\\Models\\User"),
     *                         @OA\Property(property="auditableId", type="integer", nullable=true, example=5),
     *                         @OA\Property(property="ipAddress", type="string", example="192.168.1.100"),
     *                         @OA\Property(property="userAgent", type="string", example="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"),
     *                         @OA\Property(
     *                             property="metadata",
     *                             type="object",
     *                             example={"old_cost": "50.00", "new_cost": "75.00", "reason": "Performance review"}
     *                         ),
     *                         @OA\Property(property="createdAt", type="string", format="date-time", example="2024-01-15T10:30:00Z"),
     *                         @OA\Property(property="isSensitive", type="boolean", example=true, description="Indicates if this is a security-sensitive action")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer", example=150),
     *                     @OA\Property(property="count", type="integer", example=15),
     *                     @OA\Property(property="perPage", type="integer", example=15),
     *                     @OA\Property(property="currentPage", type="integer", example=1),
     *                     @OA\Property(property="totalPages", type="integer", example=10)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User lacks view_audit_logs permission",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to view audit logs")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid filter parameters"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="filter.action",
     *                     type="array",
     *                     @OA\Items(type="string", example="Invalid action type")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        // Check permission
        if (!auth()->user()->can('view_audit_logs')) {
            return ApiResponse::forbidden('You do not have permission to view audit logs');
        }

        try {
            // Validate request parameters
            $validated = $request->validate([
                'filter.action' => [
                    'sometimes',
                    'string',
                    Rule::in([
                        'login', 'logout', 'create_project', 'update_project', 'delete_project',
                        'create_task', 'update_task', 'delete_task', 'update_internal_cost',
                        'update_role_permissions', 'create_role', 'delete_role', 'view_profitability',
                        'forbidden_access_attempt', 'approve_ui', 'pay_invoice'
                    ])
                ],
                'filter.user_id' => 'sometimes|integer|exists:users,id',
                'filter.auditable_type' => [
                    'sometimes',
                    'string',
                    Rule::in([
                        'App\\Models\\User', 'App\\Models\\Project', 'App\\Models\\Task',
                        'App\\Models\\Role', 'App\\Models\\Invoice'
                    ])
                ],
                'filter.date_from' => 'sometimes|date|before_or_equal:filter.date_to',
                'filter.date_to' => 'sometimes|date|after_or_equal:filter.date_from',
                'filter.search' => 'sometimes|string|max:255',
                'sort' => 'sometimes|string|in:created_at,-created_at,action,-action,user_id,-user_id',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100'
            ]);

            // Build query using Spatie Query Builder
            $auditLogs = QueryBuilder::for(AuditLog::class)
                ->with(['user:id,name']) // Load user relationship for efficiency
                ->allowedFilters([
                    AllowedFilter::exact('action'),
                    AllowedFilter::exact('user_id'),
                    AllowedFilter::exact('auditable_type'),
                    AllowedFilter::callback('date_from', function ($query, $value) {
                        $query->whereDate('created_at', '>=', $value);
                    }),
                    AllowedFilter::callback('date_to', function ($query, $value) {
                        $query->whereDate('created_at', '<=', $value);
                    }),
                    AllowedFilter::callback('search', function ($query, $value) {
                        $query->where(function ($q) use ($value) {
                            $q->where('auditable_id', 'LIKE', "%{$value}%")
                              ->orWhere('ip_address', 'LIKE', "%{$value}%");
                        });
                    })
                ])
                ->allowedSorts(['created_at', 'action', 'user_id'])
                ->defaultSort('-created_at') // Most recent first by default
                ->paginate($request->get('per_page', 15));

            // Format response data
            $responseData = [
                'auditLogs' => AuditLogResource::collection($auditLogs->items()),
                'pagination' => [
                    'total' => $auditLogs->total(),
                    'count' => $auditLogs->count(),
                    'perPage' => $auditLogs->perPage(),
                    'currentPage' => $auditLogs->currentPage(),
                    'totalPages' => $auditLogs->lastPage(),
                ],
            ];

            return ApiResponse::success(
                $responseData,
                'Audit logs retrieved successfully'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::validationError(
                $e->errors(),
                'Invalid filter parameters'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve audit logs: ' . $e->getMessage(),
                500
            );
        }
    }
}