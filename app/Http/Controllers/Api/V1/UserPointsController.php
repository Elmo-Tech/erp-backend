<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UserPoints\AddPointsRequest;
use App\Http\Requests\V1\UserPoints\DeductPointsRequest;
use App\Http\Requests\V1\UserPoints\SetPointsRequest;
use App\Http\Resources\V1\UserPoints\UserPointCollection;
use App\Http\Resources\V1\UserPoints\UserPointResource;
use App\Http\Resources\V1\UserPoints\UserWithPointsCollection;
use App\Http\Resources\V1\UserPoints\LeaderboardResource;
use App\Models\User;
use App\Services\HR\UserPointsService;
use App\Services\Points\UnifiedPointService;
use App\Helpers\ApiResponse;
use App\Enums\PointTransactionType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="User Points",
 *     description="User points management endpoints"
 * )
 */
class UserPointsController extends Controller
{
    public function __construct(
        private UserPointsService $userPointsService,
        private UnifiedPointService $unifiedPointService
    ) {
        // Apply middleware using Laravel 12 approach
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/user-points",
     *     summary="Get all users with their points",
     *     description="Retrieve paginated list of all employees with their current points",
     *     tags={"User Points"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"ar", "en"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by name or email",
     *         @OA\Schema(type="string", example="john")
     *     ),
     *     @OA\Parameter(
     *         name="isActive",
     *         in="query",
     *         description="Filter by active status",
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="sortBy",
     *         in="query",
     *         description="Sort field",
     *         @OA\Schema(type="string", enum={"total_points", "name", "created_at"}, example="total_points")
     *     ),
     *     @OA\Parameter(
     *         name="sortOrder",
     *         in="query",
     *         description="Sort order",
     *         @OA\Schema(type="string", enum={"asc", "desc"}, example="desc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Users with points retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Users with points retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="users",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="userId", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe"),
     *                         @OA\Property(property="email", type="string", example="john@example.com"),
     *                         @OA\Property(property="avatar", type="string", example=""),
     *                         @OA\Property(property="totalPoints", type="integer", example=150),
     *                         @OA\Property(property="isActive", type="boolean", example=true),
     *                         @OA\Property(property="createdAt", type="string", example="2026-01-07T10:00:00")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer", example=50),
     *                     @OA\Property(property="count", type="integer", example=15),
     *                     @OA\Property(property="perPage", type="integer", example=15),
     *                     @OA\Property(property="currentPage", type="integer", example=1),
     *                     @OA\Property(property="totalPages", type="integer", example=4)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->get('search'),
            'isActive' => $request->boolean('isActive'),
            'sortBy' => $request->get('sortBy', 'total_points'),
            'sortOrder' => $request->get('sortOrder', 'desc'),
        ];

        $perPage = min($request->get('perPage', 15), 100);
        $users = $this->userPointsService->getAllUsersWithPoints($filters, $perPage);

        return ApiResponse::success(
            new UserWithPointsCollection($users),
            __('Users with points retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/user-points/add",
     *     summary="Add points to a user",
     *     description="Add points to a specific user with reason tracking",
     *     tags={"User Points"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"ar", "en"}, example="en")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="userId", type="integer", example=1),
     *             @OA\Property(property="type", type="string", example="performance_bonus"),
     *             @OA\Property(property="points", type="integer", example=50),
     *             @OA\Property(property="reason", type="string", example="Excellent performance this month")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Points added successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Points added successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="userPointId", type="integer", example=1),
     *                 @OA\Property(property="userId", type="integer", example=1),
     *                 @OA\Property(property="pointsChange", type="integer", example=50),
     *                 @OA\Property(property="pointsBefore", type="integer", example=100),
     *                 @OA\Property(property="pointsAfter", type="integer", example=150),
     *                 @OA\Property(property="reason", type="string", example="Excellent performance this month"),
     *                 @OA\Property(property="type", type="string", example="manual"),
     *                 @OA\Property(property="transactionType", type="string", example="performance_bonus"),
     *                 @OA\Property(property="createdBy", type="string", example="Admin User"),
     *                 @OA\Property(property="createdAt", type="string", example="2026-01-07T10:00:00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="userId", type="array", @OA\Items(type="string", example="The user id field is required.")),
     *                 @OA\Property(property="points", type="array", @OA\Items(type="string", example="The points field is required.")),
     *                 @OA\Property(property="reason", type="array", @OA\Items(type="string", example="The reason field is required."))
     *             )
     *         )
     *     )
     * )
     */
    public function addPoints(AddPointsRequest $request): JsonResponse
    {
        $user = User::findOrFail($request->userId);
        $admin = $request->user();

        $userPoint = $this->userPointsService->addPoints(
            $user,
            $request->points,
            $request->reason,
            $admin,
            PointTransactionType::tryFrom($request->type)
        );

        return ApiResponse::success(
            new UserPointResource($userPoint->load(['user', 'createdBy'])),
            __('Points added successfully'),
            201
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/user-points/deduct",
     *     summary="Deduct points from a user",
     *     description="Deduct points from a specific user with reason tracking",
     *     tags={"User Points"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"ar", "en"}, example="en")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="userId", type="integer", example=1),
     *             @OA\Property(property="type", type="string", example="task_late"),
     *             @OA\Property(property="points", type="integer", example=25),
     *             @OA\Property(property="reason", type="string", example="Late submission of deliverables")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Points deducted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Points deducted successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="userPointId", type="integer", example=2),
     *                 @OA\Property(property="userId", type="integer", example=1),
     *                 @OA\Property(property="pointsChange", type="integer", example=-25),
     *                 @OA\Property(property="pointsBefore", type="integer", example=150),
     *                 @OA\Property(property="pointsAfter", type="integer", example=125),
     *                 @OA\Property(property="reason", type="string", example="Late submission of deliverables"),
     *                 @OA\Property(property="type", type="string", example="manual"),
     *                 @OA\Property(property="transactionType", type="string", example="task_late"),
     *                 @OA\Property(property="createdBy", type="string", example="Admin User"),
     *                 @OA\Property(property="createdAt", type="string", example="2026-01-07T10:00:00")
     *             )
     *         )
     *     )
     * )
     */
    public function deductPoints(DeductPointsRequest $request): JsonResponse
    {
        $user = User::findOrFail($request->userId);
        $admin = $request->user();

        $userPoint = $this->userPointsService->deductPoints(
            $user,
            $request->points,
            $request->reason,
            $admin,
            PointTransactionType::tryFrom($request->type)
        );

        return ApiResponse::success(
            new UserPointResource($userPoint->load(['user', 'createdBy'])),
            __('Points deducted successfully'),
            201
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/user-points/set",
     *     summary="Set user points to specific value",
     *     description="Set user points to a specific value with reason tracking",
     *     tags={"User Points"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"ar", "en"}, example="en")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="userId", type="integer", example=1),
     *             @OA\Property(property="points", type="integer", example=200),
     *             @OA\Property(property="reason", type="string", example="Monthly reset to bonus threshold")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Points set successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Points set successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="userPointId", type="integer", example=3),
     *                 @OA\Property(property="userId", type="integer", example=1),
     *                 @OA\Property(property="pointsChange", type="integer", example=75),
     *                 @OA\Property(property="pointsBefore", type="integer", example=125),
     *                 @OA\Property(property="pointsAfter", type="integer", example=200),
     *                 @OA\Property(property="reason", type="string", example="Monthly reset to bonus threshold"),
     *                 @OA\Property(property="type", type="string", example="manual"),
     *                 @OA\Property(property="createdBy", type="string", example="Admin User"),
     *                 @OA\Property(property="createdAt", type="string", example="2026-01-07T10:00:00")
     *             )
     *         )
     *     )
     * )
     */
    public function setPoints(SetPointsRequest $request): JsonResponse
    {
        $user = User::findOrFail($request->userId);
        $admin = $request->user();

        $userPoint = $this->userPointsService->setPoints(
            $user,
            $request->points,
            $request->reason,
            $admin
        );

        return ApiResponse::success(
            new UserPointResource($userPoint->load(['user', 'createdBy'])),
            __('Points set successfully'),
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/user-points/history/{userId}",
     *     summary="Get user points history",
     *     description="Get paginated history of points changes for a specific user",
     *     tags={"User Points"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"ar", "en"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User points history retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User points history retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="pointsHistory",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="userPointId", type="integer", example=1),
     *                         @OA\Property(property="pointsChange", type="integer", example=50),
     *                         @OA\Property(property="pointsBefore", type="integer", example=100),
     *                         @OA\Property(property="pointsAfter", type="integer", example=150),
     *                         @OA\Property(property="reason", type="string", example="Excellent performance"),
     *                         @OA\Property(property="type", type="string", example="manual"),
     *                         @OA\Property(property="createdBy", type="string", example="Admin User"),
     *                         @OA\Property(property="createdAt", type="string", example="2026-01-07T10:00:00")
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
     *     )
     * )
     */
    public function history(Request $request, int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        $perPage = min($request->get('perPage', 15), 100);
        
        $history = $this->userPointsService->getUserPointsHistory($user, $perPage);

        return ApiResponse::success(
            new UserPointCollection($history),
            __('User points history retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/user-points/leaderboard",
     *     summary="Get points leaderboard",
     *     description="Get top users by points for leaderboard display",
     *     tags={"User Points"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"ar", "en"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of top users to return",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leaderboard retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Leaderboard retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="userId", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="avatar", type="string", example=""),
     *                     @OA\Property(property="totalPoints", type="integer", example=350),
     *                     @OA\Property(property="rank", type="integer", example=1)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 10), 50);
        $leaderboard = $this->userPointsService->getLeaderboard($limit);

        return ApiResponse::success(
            LeaderboardResource::collection($leaderboard),
            __('Leaderboard retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/user-points/statistics",
     *     summary="Get points statistics",
     *     description="Get overall points statistics for admin dashboard",
     *     tags={"User Points"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"ar", "en"}, example="en")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Statistics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="totalUsers", type="integer", example=25),
     *                 @OA\Property(property="totalPoints", type="integer", example=5000),
     *                 @OA\Property(property="averagePoints", type="number", example=200.0),
     *                 @OA\Property(property="monthlyActivity", type="integer", example=45),
     *                 @OA\Property(
     *                     property="topUser",
     *                     type="object",
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="points", type="integer", example=350)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function statistics(): JsonResponse
    {
        $statistics = $this->userPointsService->getPointsStatistics();

        return ApiResponse::success(
            $statistics,
            __('Statistics retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/user-points/report",
     *     summary="Get points activity report",
     *     description="Get detailed report of points activities within a date range",
     *     tags={"User Points"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"ar", "en"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="dateFrom",
     *         in="query",
     *         description="Start date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date", example="2026-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="dateTo",
     *         in="query",
     *         description="End date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date", example="2026-01-31")
     *     ),
     *     @OA\Parameter(
     *         name="userId",
     *         in="query",
     *         description="Filter by specific user ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by activity type",
     *         @OA\Schema(type="string", enum={"manual", "automatic"}, example="manual")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", example=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Points report retrieved successfully"
     *     )
     * )
     */
    public function report(Request $request): JsonResponse
    {
        $filters = [
            'dateFrom' => $request->get('dateFrom'),
            'dateTo' => $request->get('dateTo'),
            'userId' => $request->get('userId'),
            'type' => $request->get('type'),
        ];

        $perPage = min($request->get('perPage', 20), 100);
        $report = $this->userPointsService->getPointsReport($filters, $perPage);

                return ApiResponse::success(

                    $report,

                    __('Points report retrieved successfully')

                );

            }

        

            /**

             * @OA\Get(

             *     path="/api/v1/user-points/transaction-types",

             *     summary="Get all point transaction types",

             *     description="Retrieve a list of all available point transaction types for selection",

             *     tags={"User Points"},

             *     security={{"sanctum":{}}},

             *     @OA\Response(

             *         response=200,

             *         description="Transaction types retrieved successfully",

             *         @OA\JsonContent(

             *             @OA\Property(property="success", type="boolean", example=true),

             *             @OA\Property(property="message", type="string", example="Transaction types retrieved successfully"),

             *             @OA\Property(

             *                 property="data",

             *                 type="array",

             *                 @OA\Items(

             *                     @OA\Property(property="name", type="string", example="TASK_COMPLETED"),

             *                     @OA\Property(property="value", type="string", example="task_completed"),

             *                     @OA\Property(property="label", type="string", example="Task Completed"),

             *                     @OA\Property(property="points", type="integer", example=10)

             *                 )

             *             )

             *         )

             *     )

             * )

             */

            public function getTransactionTypes(): JsonResponse

            {

                $types = collect(PointTransactionType::cases())->map(function ($type) {

                    return [

                        'name' => $type->name,

                        'value' => $type->value,

                        'label' => $type->label(),

                        'points' => $type->defaultPoints(),

                        'is_positive' => $type->isPositive(),

                    ];

                });

        

                return ApiResponse::success(

                    $types,

                    __('Transaction types retrieved successfully')

                );

            }

    /**
     * Get unified user points history from both systems
     */
    public function getUnifiedHistory(Request $request, int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        
        $filters = [];
        if ($request->has('dateFrom')) {
            $filters['dateFrom'] = $request->dateFrom;
        }
        if ($request->has('dateTo')) {
            $filters['dateTo'] = $request->dateTo;
        }
        
        $limit = $request->get('limit', 50);
        
        $history = $this->unifiedPointService->getUserHistory($user, $limit, $filters);
        $summary = $this->unifiedPointService->getUserSummary(
            $user, 
            $filters['dateFrom'] ?? null, 
            $filters['dateTo'] ?? null
        );
        
        return ApiResponse::success([
            'transactions' => $history,
            'summary' => $summary,
            'currentPoints' => $user->total_points ?? 0,
        ], __('Unified points history retrieved successfully'));
    }

        }

        
