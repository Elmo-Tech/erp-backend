<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Reporting\EmployeeReviewService;
use App\Services\Reporting\PdfService;
use App\Services\Reporting\ProfitabilityService;
use App\Services\Reporting\QualityService;
use App\Services\Reporting\UserPerformanceService;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use OpenApi\Annotations as OA;
use RuntimeException;
use Throwable;

class AdminStatsController extends Controller
{
    public function __construct(
        private ProfitabilityService $profitabilityService,
        private QualityService $qualityService,
        private UserPerformanceService $userPerformanceService,
        private EmployeeReviewService $employeeReviewService,
        private PdfService $pdfService
    ) {
        // Apply middleware using Laravel 12 approach
        $this->middleware(['auth:sanctum', 'locale']);
    }

    private function canAccessReport(string ...$permissions): bool
    {
        $user = auth()->user();

        // Match the frontend's super-admin access even when role permissions are incomplete.
        return $user !== null && ($user->hasRole('super-admin') || $user->can($permissions));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/stats/overview",
     *     operationId="getAdminStatsOverview",
     *     tags={"Admin Stats"},
     *     summary="Get admin dashboard overview statistics",
     *     description="Retrieve comprehensive dashboard statistics including profitability, quality metrics, and performance overview. Requires view_admin_stats permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Admin overview statistics retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Admin overview statistics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="profitability",
     *                     type="object",
     *                     @OA\Property(property="totalRevenue", type="string", example="125000.00"),
     *                     @OA\Property(property="totalProfit", type="string", example="45000.00"),
     *                     @OA\Property(property="overallMargin", type="string", example="36.00"),
     *                     @OA\Property(property="activeProjects", type="integer", example=8),
     *                     @OA\Property(property="completedProjects", type="integer", example=15)
     *                 ),
     *                 @OA\Property(
     *                     property="quality",
     *                     type="object",
     *                     @OA\Property(property="overallBounceRate", type="string", example="0.15"),
     *                     @OA\Property(property="qualityScore", type="string", example="85.00"),
     *                     @OA\Property(property="totalBounces", type="integer", example=23),
     *                     @OA\Property(property="tasksWithBounces", type="integer", example=18)
     *                 ),
     *                 @OA\Property(
     *                     property="attendance",
     *                     type="object",
     *                     @OA\Property(property="totalLateMinutes", type="integer", example=120),
     *                     @OA\Property(property="averageIdleTime", type="string", example="45.50"),
     *                     @OA\Property(
     *                         property="usersWithExcessiveIdle",
     *                         type="array",
     *
     *                         @OA\Items(
     *
     *                             @OA\Property(property="userId", type="integer", example=3),
     *                             @OA\Property(property="userName", type="string", example="John Doe"),
     *                             @OA\Property(property="totalExcessiveIdleMinutes", type="integer", example=180),
     *                             @OA\Property(property="daysWithExcessiveIdle", type="integer", example=3),
     *                             @OA\Property(property="averageExcessiveIdlePerDay", type="string", example="60.00")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User lacks view_admin_stats permission",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to view admin statistics")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function overview(): JsonResponse
    {
        // Check permission
        if (! $this->canAccessReport('view_admin_stats')) {
            return ApiResponse::forbidden('You do not have permission to view admin statistics');
        }

        try {
            $activeProjectStatuses = ['active', 'ui_ux', 'development', 'testing'];
            $totalProjects = Project::withoutGlobalScopes()->count();
            $activeProjects = Project::withoutGlobalScopes()->whereIn('status', $activeProjectStatuses)->count();
            $completedProjects = Project::withoutGlobalScopes()->where('status', 'completed')->count();
            $openTasks = Task::whereIn('status', ['todo', 'in_progress'])->count();
            $completedTasks = Task::where('status', 'completed')->count();
            $overdueTasks = Task::whereNotNull('due_date')
                ->whereDate('due_date', '<', Carbon::today())
                ->where('status', '!=', 'completed')
                ->count();
            $activeClients = Client::whereHas('projects', function ($query) use ($activeProjectStatuses) {
                $query->whereIn('status', $activeProjectStatuses);
            })->count();
            $employeesCount = User::whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'manager', 'developer']))->count();
            $todayAttendance = Attendance::whereDate('clock_in', Carbon::today())->count();
            $trackedMinutesToday = TimeEntry::whereDate('start_time', Carbon::today())
                ->whereNotNull('end_time')
                ->sum('duration_minutes');

            $completionRate = $openTasks + $completedTasks > 0
                ? round(($completedTasks / ($openTasks + $completedTasks)) * 100, 2)
                : 0;

            $overviewData = [
                'projects' => [
                    'total' => $totalProjects,
                    'active' => $activeProjects,
                    'completed' => $completedProjects,
                ],
                'tasks' => [
                    'open' => $openTasks,
                    'completed' => $completedTasks,
                    'overdue' => $overdueTasks,
                    'completionRate' => $completionRate,
                ],
                'clients' => [
                    'active' => $activeClients,
                ],
                'employees' => [
                    'total' => $employeesCount,
                ],
                'attendance' => [
                    'todayClockIns' => $todayAttendance,
                    'trackedHoursToday' => round($trackedMinutesToday / 60, 2),
                ],
                // Compatibility fields used by the simplified frontend reports.
                'activeProjects' => $activeProjects,
                'completedProjects' => $completedProjects,
                'teamUtilization' => $employeesCount > 0 ? round(($todayAttendance / $employeesCount) * 100, 2) : 0,
            ];

            return ApiResponse::success(
                $overviewData,
                'Admin overview statistics retrieved successfully'
            );
        } catch (Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve admin overview statistics: '.$e->getMessage(),
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/stats/profitability",
     *     operationId="getAdminStatsProfitability",
     *     tags={"Admin Stats"},
     *     summary="Get project profitability report",
     *     description="Retrieve detailed profitability report for all projects with filtering and pagination. Requires both view_admin_stats AND view_internal_costs permissions.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter projects by status",
     *
     *         @OA\Schema(type="string", enum={"active", "completed", "on_hold", "cancelled"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="dateFrom",
     *         in="query",
     *         description="Filter projects created from this date (YYYY-MM-DD)",
     *
     *         @OA\Schema(type="string", format="date", example="2024-01-01")
     *     ),
     *
     *     @OA\Parameter(
     *         name="dateTo",
     *         in="query",
     *         description="Filter projects created up to this date (YYYY-MM-DD)",
     *
     *         @OA\Schema(type="string", format="date", example="2024-12-31")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sortBy",
     *         in="query",
     *         description="Sort results by field",
     *
     *         @OA\Schema(type="string", enum={"profit", "margin", "budget"}, example="profit")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sortDirection",
     *         in="query",
     *         description="Sort direction",
     *
     *         @OA\Schema(type="string", enum={"asc", "desc"}, example="desc")
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="Number of items per page",
     *
     *         @OA\Schema(type="integer", minimum=1, maximum=100, example=15)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Profitability report retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Profitability report retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="projects",
     *                     type="array",
     *
     *                     @OA\Items(
     *
     *                         @OA\Property(property="projectId", type="integer", example=1),
     *                         @OA\Property(property="title", type="string", example="E-commerce Website"),
     *                         @OA\Property(property="budget", type="string", example="50000.00"),
     *                         @OA\Property(property="totalCost", type="string", example="35000.00"),
     *                         @OA\Property(property="profit", type="string", example="15000.00"),
     *                         @OA\Property(property="profitMargin", type="string", example="30.00")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer", example=25),
     *                     @OA\Property(property="count", type="integer", example=15),
     *                     @OA\Property(property="perPage", type="integer", example=15),
     *                     @OA\Property(property="currentPage", type="integer", example=1),
     *                     @OA\Property(property="totalPages", type="integer", example=2)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User lacks required permissions",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to view profitability data")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid filter parameters"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="sortBy",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="Invalid sort field. Allowed: profit, margin, budget")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function profitability(Request $request): JsonResponse
    {
        // Check both required permissions
        if (! $this->canAccessReport('view_admin_stats', 'view_internal_costs')) {
            return ApiResponse::forbidden('You do not have permission to view profitability data');
        }

        try {
            // Extract and validate filters
            $filters = [];
            if ($request->has('status')) {
                $filters['status'] = $request->get('status');
            }
            if ($request->has('dateFrom')) {
                $filters['dateFrom'] = $request->get('dateFrom');
            }
            if ($request->has('dateTo')) {
                $filters['dateTo'] = $request->get('dateTo');
            }
            // Extract sorting parameters
            $sortBy = $request->get('sortBy', 'profit');
            $sortDirection = $request->get('sortDirection', 'desc');
            $perPage = (int) $request->get('perPage', 15);

            // Get profitability report
            $profitabilityReport = $this->profitabilityService->getProfitabilityReport(
                $filters,
                $sortBy,
                $sortDirection,
                $perPage
            );

            // Format response data
            $responseData = [
                'projects' => $profitabilityReport->items(),
                'pagination' => [
                    'total' => $profitabilityReport->total(),
                    'count' => $profitabilityReport->count(),
                    'perPage' => $profitabilityReport->perPage(),
                    'currentPage' => $profitabilityReport->currentPage(),
                    'totalPages' => $profitabilityReport->lastPage(),
                ],
            ];

            return ApiResponse::success(
                $responseData,
                'Profitability report retrieved successfully'
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::validationError(
                ['sortBy' => [$e->getMessage()]],
                'Invalid filter parameters'
            );
        } catch (Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve profitability report: '.$e->getMessage(),
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/stats/quality",
     *     operationId="getAdminStatsQuality",
     *     tags={"Admin Stats"},
     *     summary="Get quality metrics including bounce rates",
     *     description="Retrieve comprehensive quality metrics including bounce rates by developer and top bouncing tasks. Requires view_admin_stats permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="dateFrom",
     *         in="query",
     *         description="Filter data from this date (YYYY-MM-DD)",
     *
     *         @OA\Schema(type="string", format="date", example="2024-01-01")
     *     ),
     *
     *     @OA\Parameter(
     *         name="dateTo",
     *         in="query",
     *         description="Filter data up to this date (YYYY-MM-DD)",
     *
     *         @OA\Schema(type="string", format="date", example="2024-12-31")
     *     ),
     *
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Limit for top bouncing tasks",
     *
     *         @OA\Schema(type="integer", minimum=1, maximum=50, example=10)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Quality metrics retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Quality metrics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="overview",
     *                     type="object",
     *                     @OA\Property(property="overallBounceRate", type="string", example="0.15"),
     *                     @OA\Property(property="qualityScore", type="string", example="85.00"),
     *                     @OA\Property(property="totalBounces", type="integer", example=23),
     *                     @OA\Property(property="tasksWithBounces", type="integer", example=18)
     *                 ),
     *                 @OA\Property(
     *                     property="bounceRateByDeveloper",
     *                     type="array",
     *
     *                     @OA\Items(
     *
     *                         @OA\Property(property="userId", type="integer", example=1),
     *                         @OA\Property(property="userName", type="string", example="John Doe"),
     *                         @OA\Property(property="totalBounces", type="integer", example=5),
     *                         @OA\Property(property="totalTasks", type="integer", example=20),
     *                         @OA\Property(property="averageBounceRate", type="string", example="0.25")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="topBouncingTasks",
     *                     type="array",
     *
     *                     @OA\Items(
     *
     *                         @OA\Property(property="taskId", type="integer", example=15),
     *                         @OA\Property(property="title", type="string", example="Fix login bug"),
     *                         @OA\Property(property="projectTitle", type="string", example="E-commerce Website"),
     *                         @OA\Property(property="assigneeName", type="string", example="Jane Smith"),
     *                         @OA\Property(property="reopenCount", type="integer", example=3),
     *                         @OA\Property(property="status", type="string", example="completed")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User lacks view_admin_stats permission",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to view admin statistics")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function quality(Request $request): JsonResponse
    {
        // Check permission
        if (! $this->canAccessReport('view_admin_stats')) {
            return ApiResponse::forbidden('You do not have permission to view admin statistics');
        }

        try {
            // Extract filters
            $filters = [];
            if ($request->has('dateFrom')) {
                $filters['dateFrom'] = $request->get('dateFrom');
            }
            if ($request->has('dateTo')) {
                $filters['dateTo'] = $request->get('dateTo');
            }
            $limit = (int) $request->get('limit', 10);

            // Get quality overview
            $qualityOverview = $this->qualityService->getQualityOverview();

            // Get bounce rate by developer
            $bounceRateByDeveloper = $this->qualityService->getBounceRateByDeveloper($filters);

            // Get top bouncing tasks
            $topBouncingTasks = $this->qualityService->getTopBouncingTasks($limit, $filters);

            $qualityData = [
                'overview' => $qualityOverview,
                'bounceRateByDeveloper' => $bounceRateByDeveloper,
                'topBouncingTasks' => $topBouncingTasks,
            ];

            return ApiResponse::success(
                $qualityData,
                'Quality metrics retrieved successfully'
            );
        } catch (Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve quality metrics: '.$e->getMessage(),
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/stats/user-performance",
     *     operationId="getAdminStatsUserPerformance",
     *     tags={"Admin Stats"},
     *     summary="Get user performance metrics",
     *     description="Retrieve comprehensive user performance metrics including tracked time, idle time, task completion rates, and bounce rates. Requires view_admin_stats permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="dateFrom",
     *         in="query",
     *         description="Filter data from this date (YYYY-MM-DD)",
     *
     *         @OA\Schema(type="string", format="date", example="2024-01-01")
     *     ),
     *
     *     @OA\Parameter(
     *         name="dateTo",
     *         in="query",
     *         description="Filter data up to this date (YYYY-MM-DD)",
     *
     *         @OA\Schema(type="string", format="date", example="2024-12-31")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User performance metrics retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User performance metrics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="userId", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="totalTrackedMinutes", type="integer", example=2400),
     *                     @OA\Property(property="totalIdleMinutes", type="integer", example=120),
     *                     @OA\Property(property="taskCompletionRate", type="string", example="85.50"),
     *                     @OA\Property(property="averageBounceRate", type="string", example="0.15"),
     *                     @OA\Property(property="attendanceDays", type="integer", example=22)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User lacks view_admin_stats permission",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to view admin statistics")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function userPerformance(Request $request): JsonResponse
    {
        // Check permission
        if (! $this->canAccessReport('view_admin_stats')) {
            return ApiResponse::forbidden('You do not have permission to view admin statistics');
        }

        try {
            // Extract filters
            $filters = [];
            if ($request->has('dateFrom')) {
                $filters['dateFrom'] = $request->get('dateFrom');
            }
            if ($request->has('dateTo')) {
                $filters['dateTo'] = $request->get('dateTo');
            }

            // Get all user performance metrics
            $userPerformanceData = $this->userPerformanceService->getAllUserPerformance($filters);

            return ApiResponse::success(
                $userPerformanceData,
                'User performance metrics retrieved successfully'
            );
        } catch (Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve user performance metrics: '.$e->getMessage(),
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/stats/attendance",
     *     operationId="getAdminStatsAttendance",
     *     tags={"Admin Stats"},
     *     summary="Get attendance analytics",
     *     description="Retrieve comprehensive attendance analytics including late arrivals, idle time statistics, and users with excessive idle time. Requires view_admin_stats permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="dateFrom",
     *         in="query",
     *         description="Filter data from this date (YYYY-MM-DD)",
     *
     *         @OA\Schema(type="string", format="date", example="2024-01-01")
     *     ),
     *
     *     @OA\Parameter(
     *         name="dateTo",
     *         in="query",
     *         description="Filter data up to this date (YYYY-MM-DD)",
     *
     *         @OA\Schema(type="string", format="date", example="2024-12-31")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Attendance analytics retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Attendance analytics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="totalLateMinutes", type="integer", example=120),
     *                 @OA\Property(property="averageIdleTime", type="string", example="45.50"),
     *                 @OA\Property(
     *                     property="usersWithExcessiveIdle",
     *                     type="array",
     *
     *                     @OA\Items(
     *
     *                         @OA\Property(property="userId", type="integer", example=3),
     *                         @OA\Property(property="userName", type="string", example="John Doe"),
     *                         @OA\Property(property="totalExcessiveIdleMinutes", type="integer", example=180),
     *                         @OA\Property(property="daysWithExcessiveIdle", type="integer", example=3),
     *                         @OA\Property(property="averageExcessiveIdlePerDay", type="string", example="60.00")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User lacks view_admin_stats permission",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to view admin statistics")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function attendance(Request $request): JsonResponse
    {
        // Check permission
        if (! $this->canAccessReport('view_admin_stats')) {
            return ApiResponse::forbidden('You do not have permission to view admin statistics');
        }

        try {
            // Extract filters
            $filters = [];
            if ($request->has('dateFrom')) {
                $filters['dateFrom'] = $request->get('dateFrom');
            }
            if ($request->has('dateTo')) {
                $filters['dateTo'] = $request->get('dateTo');
            }

            // Get attendance analytics
            $attendanceData = $this->userPerformanceService->getAttendanceAnalytics($filters);

            return ApiResponse::success(
                $attendanceData,
                'Attendance analytics retrieved successfully'
            );
        } catch (Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve attendance analytics: '.$e->getMessage(),
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/stats/employee-review/{userId}",
     *     operationId="getEmployeeReview",
     *     tags={"Admin Stats"},
     *     summary="Get comprehensive employee review",
     *     description="Retrieve detailed employee performance review including time tracking, tasks, projects, attendance, and productivity metrics. Requires view_admin_stats permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="dateFrom",
     *         in="query",
     *         description="Filter data from this date (YYYY-MM-DD)",
     *
     *         @OA\Schema(type="string", format="date", example="2024-01-01")
     *     ),
     *
     *     @OA\Parameter(
     *         name="dateTo",
     *         in="query",
     *         description="Filter data up to this date (YYYY-MM-DD)",
     *
     *         @OA\Schema(type="string", format="date", example="2024-12-31")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Employee review retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee review retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="employee",
     *                     type="object",
     *                     @OA\Property(property="userId", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com"),
     *                     @OA\Property(property="avatar", type="string", example="https://example.com/avatar.jpg"),
     *                     @OA\Property(property="specialization", type="string", example="frontend")
     *                 ),
     *                 @OA\Property(
     *                     property="period",
     *                     type="object",
     *                     @OA\Property(property="dateFrom", type="string", example="2024-01-01"),
     *                     @OA\Property(property="dateTo", type="string", example="2024-12-31")
     *                 ),
     *                 @OA\Property(
     *                     property="overview",
     *                     type="object",
     *                     @OA\Property(property="totalTrackedHours", type="number", example=160.5),
     *                     @OA\Property(property="totalIdleHours", type="number", example=12.3),
     *                     @OA\Property(property="taskCompletionRate", type="string", example="85.50"),
     *                     @OA\Property(property="averageBounceRate", type="string", example="0.15"),
     *                     @OA\Property(property="attendanceDays", type="integer", example=22),
     *                     @OA\Property(property="productivityScore", type="string", example="82.45")
     *                 ),
     *                 @OA\Property(
     *                     property="timeTracking",
     *                     type="object",
     *                     @OA\Property(property="totalEntries", type="integer", example=150),
     *                     @OA\Property(property="totalMinutes", type="integer", example=9630),
     *                     @OA\Property(property="totalHours", type="number", example=160.5)
     *                 ),
     *                 @OA\Property(
     *                     property="tasks",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer", example=45),
     *                     @OA\Property(property="completed", type="integer", example=38),
     *                     @OA\Property(property="inProgress", type="integer", example=5),
     *                     @OA\Property(property="totalBounces", type="integer", example=7)
     *                 ),
     *                 @OA\Property(
     *                     property="projects",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer", example=5),
     *                     @OA\Property(property="active", type="integer", example=2),
     *                     @OA\Property(property="completed", type="integer", example=3)
     *                 ),
     *                 @OA\Property(
     *                     property="attendance",
     *                     type="object",
     *                     @OA\Property(property="totalDays", type="integer", example=22),
     *                     @OA\Property(property="lateDays", type="integer", example=3),
     *                     @OA\Property(property="totalLateMinutes", type="integer", example=45),
     *                     @OA\Property(property="totalIdleMinutes", type="integer", example=738),
     *                     @OA\Property(property="averageIdlePerDay", type="number", example=33.55)
     *                 ),
     *                 @OA\Property(
     *                     property="productivity",
     *                     type="object",
     *                     @OA\Property(property="efficiency", type="string", example="92.85"),
     *                     @OA\Property(property="qualityScore", type="string", example="85.00"),
     *                     @OA\Property(property="completionRate", type="string", example="85.50"),
     *                     @OA\Property(property="productivityScore", type="string", example="82.45")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User lacks view_admin_stats permission",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to view admin statistics")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function employeeReview(Request $request, int $userId): JsonResponse
    {
        // Check permission
        if (! $this->canAccessReport('view_admin_stats')) {
            return ApiResponse::forbidden('You do not have permission to view admin statistics');
        }

        try {
            // Extract filters
            $filters = [];
            if ($request->has('dateFrom')) {
                $filters['dateFrom'] = $request->get('dateFrom');
            }
            if ($request->has('dateTo')) {
                $filters['dateTo'] = $request->get('dateTo');
            }
            if ($request->has('page')) {
                $filters['page'] = (int) $request->get('page');
            }
            if ($request->has('perPage')) {
                $filters['perPage'] = (int) $request->get('perPage');
            }

            // Add debug logging
            Log::info('Employee Review Request', [
                'userId' => $userId,
                'filters' => $filters,
                'user' => auth()->user()->name,
            ]);

            // Get employee review data
            $reviewData = $this->employeeReviewService->getEmployeeReview($userId, $filters);

            // Add debug logging for response
            Log::info('Employee Review Response Structure', [
                'userId' => $userId,
                'projects_count' => $reviewData['projects']['total'] ?? 'N/A',
                'trends_weeks' => count($reviewData['trends']['weeklyTimeTracking'] ?? []),
                'has_projects_array' => isset($reviewData['projects']['projects']),
                'projects_array_count' => count($reviewData['projects']['projects'] ?? []),
            ]);

            return ApiResponse::success(
                $reviewData,
                'Employee review retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('User not found', 404);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::validationError(
                ['dateRange' => [$e->getMessage()]],
                'Invalid date range'
            );
        } catch (Throwable $e) {
            Log::error('Employee Review Error', [
                'userId' => $userId,
                'filters' => $filters ?? [],
                'exception' => $e::class,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->boolean('debug') || config('app.debug')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to retrieve employee review',
                    'debug' => [
                        'exception' => $e::class,
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'filters' => $filters ?? [],
                        'trace' => collect($e->getTrace())->take(10)->values(),
                    ],
                ], 500);
            }

            return ApiResponse::error('Failed to retrieve employee review: '.$e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/stats/employee-review/{userId}/export",
     *     summary="Export employee review as PDF",
     *     description="Export detailed employee performance review as a downloadable PDF report",
     *     operationId="exportEmployeeReview",
     *     tags={"Admin Stats"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="ID of the user to export review for",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="dateFrom",
     *         in="query",
     *         required=false,
     *         description="Start date for the review period (YYYY-MM-DD)",
     *
     *         @OA\Schema(type="string", format="date", example="2026-01-01")
     *     ),
     *
     *     @OA\Parameter(
     *         name="dateTo",
     *         in="query",
     *         required=false,
     *         description="End date for the review period (YYYY-MM-DD)",
     *
     *         @OA\Schema(type="string", format="date", example="2026-01-22")
     *     ),
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="PDF file download",
     *
     *         @OA\MediaType(
     *             mediaType="application/pdf",
     *
     *             @OA\Schema(
     *                 type="string",
     *                 format="binary",
     *                 description="PDF file containing employee performance review"
     *             )
     *         ),
     *
     *         @OA\Header(
     *             header="Content-Disposition",
     *             description="Attachment filename",
     *
     *             @OA\Schema(type="string", example="attachment; filename=employee_review_John_Doe_2026-01-01_to_2026-01-22.pdf")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Access denied - insufficient permissions",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Access denied. You do not have permission to view admin statistics.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error during PDF generation",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to export employee review: PDF generation error")
     *         )
     *     )
     * )
     *
     * Export employee review as PDF report.
     */
    public function exportEmployeeReview(Request $request, int $userId)
    {
        try {
            // Check permission
            if (! $this->canAccessReport('view_admin_stats')) {
                return ApiResponse::error(
                    'Access denied. You do not have permission to view admin statistics.',
                    403
                );
            }

            // Validate user exists
            $user = User::findOrFail($userId);

            // Get filters
            $filters = [];
            if ($request->has('dateFrom')) {
                $filters['dateFrom'] = $request->get('dateFrom');
            }
            if ($request->has('dateTo')) {
                $filters['dateTo'] = $request->get('dateTo');
            }
            if ($request->has('page')) {
                $filters['page'] = (int) $request->get('page');
            }
            if ($request->has('perPage')) {
                $filters['perPage'] = (int) $request->get('perPage');
            }

            // Get locale from request header (Accept-Language)
            $locale = $request->header('Accept-Language', 'en');
            if (! in_array($locale, ['ar', 'en'])) {
                $locale = 'en';
            }

            // Set locale for translations
            app()->setLocale($locale);

            // Get employee review data
            $reviewData = $this->employeeReviewService->getEmployeeReview($userId, $filters);

            // Add locale to review data for PDF generation
            $reviewData['locale'] = $locale;

            // Add forPdf flag to get ALL transactions without pagination
            $filters['forPdf'] = true;

            // Get complete points data for PDF (all transactions)
            $completePointsData = $this->employeeReviewService->getEmployeeReview($userId, $filters);
            $reviewData['points'] = $completePointsData['points'];

            try {
                // Generate filename with date range if provided
                $dateRange = '';
                if (isset($filters['dateFrom']) && isset($filters['dateTo'])) {
                    $dateRange = '_'.$filters['dateFrom'].'_to_'.$filters['dateTo'];
                }
                $fileName = 'Employee Review'.str_replace(' ', ' ', $user->name).$dateRange.'.pdf';

                // Use PdfService for robust PDF generation with Arabic-safe options
                $pdfOptions = [
                    'defaultFont' => 'DejaVu Sans', // Always use DejaVu Sans for Arabic support
                    'isRemoteEnabled' => false, // Disable for security and reliability
                    'enableFontSubsetting' => false, // Critical: Never enable for Arabic text
                ];

                return $this->pdfService->downloadPdf('reports.employee-review', $reviewData, $fileName, $pdfOptions);

            } catch (Throwable $pdfException) {
                // Log the error for debugging
                Log::error('Employee Review PDF Export Failed', [
                    'user_id' => $userId,
                    'filters' => $filters,
                    'error' => $pdfException->getMessage(),
                    'trace' => $pdfException->getTraceAsString(),
                ]);

                throw new RuntimeException('PDF generation failed: '.$pdfException->getMessage(), previous: $pdfException);
            }

        } catch (ModelNotFoundException $e) {
            return ApiResponse::error(
                'User not found',
                404
            );
        } catch (Throwable $e) {
            Log::error('Employee Review Export Error', [
                'userId' => $userId,
                'filters' => $filters ?? [],
                'exception' => $e::class,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->boolean('debug') || config('app.debug')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to export employee review',
                    'debug' => [
                        'exception' => $e::class,
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'filters' => $filters ?? [],
                        'trace' => collect($e->getTrace())->take(10)->values(),
                    ],
                ], 500);
            }

            return ApiResponse::error('Failed to export employee review: '.$e->getMessage(), 500);
        }
    }

    /**
     * Ensure PDF directories exist with proper permissions
     */
    private function ensurePdfDirectoriesExist(): void
    {
        $directories = [
            storage_path('app/fonts'),
            storage_path('fonts'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
        ];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                try {
                    mkdir($directory, 0755, true);

                    // Create .gitkeep file to ensure directory is tracked
                    $gitkeepFile = $directory.'/.gitkeep';
                    if (! file_exists($gitkeepFile)) {
                        file_put_contents($gitkeepFile, '');
                    }

                    Log::info("Created PDF directory: {$directory}");
                } catch (Exception $e) {
                    Log::warning("Failed to create PDF directory {$directory}: ".$e->getMessage());
                }
            }
        }
    }
}
