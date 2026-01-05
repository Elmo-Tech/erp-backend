<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Performance\CreateInnovationLogRequest;
use App\Http\Resources\V1\Performance\InnovationLogResource;
use App\Http\Resources\V1\Performance\InnovationLogCollection;
use App\Models\InnovationLog;
use App\Services\Performance\InnovationTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Innovation Logs",
 *     description="Track innovation, AI tools usage, and process improvements"
 * )
 */
class InnovationLogController extends Controller
{
    public function __construct(
        private InnovationTrackingService $innovationService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/innovation-logs",
     *     operationId="getInnovationLogs",
     *     tags={"Innovation Logs"},
     *     summary="Get innovation logs",
     *     description="Returns paginated list of innovation logs. Users see their own logs unless they have view_all_innovation_logs permission.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *     @OA\Parameter(
     *         name="filter[userId]",
     *         in="query",
     *         required=false,
     *         description="Filter by user ID (requires view_all_innovation_logs permission)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="filter[category]",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"ai_tool", "automation", "new_tech", "process_improvement"})
     *     ),
     *     @OA\Parameter(
     *         name="filter[isVerified]",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"true", "false"})
     *     ),
     *     @OA\Parameter(
     *         name="filter[impactLevel]",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"low", "medium", "high"})
     *     ),
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="innovationLogs", type="array", @OA\Items(
     *                     @OA\Property(property="logId", type="integer"),
     *                     @OA\Property(property="userId", type="integer"),
     *                     @OA\Property(property="userName", type="string"),
     *                     @OA\Property(property="toolName", type="string"),
     *                     @OA\Property(property="category", type="string"),
     *                     @OA\Property(property="categoryLabel", type="string"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="impactLevel", type="string"),
     *                     @OA\Property(property="isVerified", type="boolean")
     *                 ))
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = InnovationLog::with(['user', 'project', 'task', 'verifier']);

        // Filter by user (admin can see all, users see their own)
        if (!$request->user()->can('view_all_innovation_logs')) {
            $query->where('user_id', $request->user()->id);
        } elseif ($request->has('filter.userId')) {
            $query->where('user_id', $request->query('filter.userId'));
        }

        if ($request->has('filter.category')) {
            $query->where('category', $request->query('filter.category'));
        }

        if ($request->has('filter.isVerified')) {
            $query->where('is_verified', $request->query('filter.isVerified') === 'true');
        }

        if ($request->has('filter.impactLevel')) {
            $query->where('impact_level', $request->query('filter.impactLevel'));
        }

        $logs = $query->orderByDesc('created_at')
            ->paginate($request->query('perPage', 15));

        return ApiResponse::success(new InnovationLogCollection($logs));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/innovation-logs",
     *     operationId="createInnovationLog",
     *     tags={"Innovation Logs"},
     *     summary="Log a new innovation",
     *     description="Creates a new innovation log entry. Employees can log their use of AI tools, automation scripts, or new technologies.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"toolName", "category", "description"},
     *             @OA\Property(property="projectId", type="integer", description="Related project ID (optional)"),
     *             @OA\Property(property="taskId", type="integer", description="Related task ID (optional)"),
     *             @OA\Property(property="toolName", type="string", example="GitHub Copilot", description="Name of the tool or technology"),
     *             @OA\Property(property="category", type="string", enum={"ai_tool", "automation", "new_tech", "process_improvement"}),
     *             @OA\Property(property="description", type="string", description="What was done and how"),
     *             @OA\Property(property="impactDescription", type="string", description="How it helped or improved work"),
     *             @OA\Property(property="impactLevel", type="string", enum={"low", "medium", "high"}, default="medium"),
     *             @OA\Property(property="timeSavedMinutes", type="integer", description="Estimated time saved in minutes")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function store(CreateInnovationLogRequest $request): JsonResponse
    {
        $log = $this->innovationService->logInnovation(
            $request->user(),
            $request->validated()
        );

        return ApiResponse::success(
            new InnovationLogResource($log),
            __('messages.innovation_logged'),
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/innovation-logs/{id}",
     *     operationId="getInnovationLog",
     *     tags={"Innovation Logs"},
     *     summary="Get a specific innovation log",
     *     description="Returns detailed innovation log. Users can view their own logs.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $log = InnovationLog::with(['user', 'project', 'task', 'verifier'])->findOrFail($id);

        // Users can view their own logs
        if ($log->user_id !== $request->user()->id && !$request->user()->can('view_all_innovation_logs')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        return ApiResponse::success(new InnovationLogResource($log));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/innovation-logs/{id}/verify",
     *     operationId="verifyInnovationLog",
     *     tags={"Innovation Logs"},
     *     summary="Verify an innovation log",
     *     description="Marks an innovation log as verified. Requires verify_innovation_logs permission.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function verify(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->can('verify_innovation_logs')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $log = InnovationLog::findOrFail($id);
        $log = $this->innovationService->verifyInnovation($log, $request->user());

        return ApiResponse::success(
            new InnovationLogResource($log),
            __('messages.innovation_verified')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/innovation-logs/{id}/reject",
     *     operationId="rejectInnovationLog",
     *     tags={"Innovation Logs"},
     *     summary="Reject an innovation log",
     *     description="Marks an innovation log as rejected/unverified. Requires verify_innovation_logs permission.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->can('verify_innovation_logs')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $log = InnovationLog::findOrFail($id);
        $log = $this->innovationService->rejectInnovation($log);

        return ApiResponse::success(
            new InnovationLogResource($log),
            __('messages.innovation_rejected')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/innovation-logs/pending",
     *     operationId="getPendingInnovationLogs",
     *     tags={"Innovation Logs"},
     *     summary="Get pending innovation logs for verification",
     *     description="Returns list of unverified innovation logs. Requires verify_innovation_logs permission.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized")
     * )
     */
    public function pending(Request $request): JsonResponse
    {
        if (!$request->user()->can('verify_innovation_logs')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $logs = $this->innovationService->getPendingInnovations();

        return ApiResponse::success(InnovationLogResource::collection($logs));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/innovation-logs/my-summary",
     *     operationId="getMyInnovationSummary",
     *     tags={"Innovation Logs"},
     *     summary="Get current user's innovation summary",
     *     description="Returns summary of the authenticated user's innovation logs for a date range.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *     @OA\Parameter(
     *         name="startDate",
     *         in="query",
     *         required=false,
     *         description="Start date (defaults to start of current month)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="endDate",
     *         in="query",
     *         required=false,
     *         description="End date (defaults to end of current month)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="logs", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="summary", type="object",
     *                     @OA\Property(property="total", type="integer"),
     *                     @OA\Property(property="verified", type="integer"),
     *                     @OA\Property(property="pending", type="integer"),
     *                     @OA\Property(property="byCategory", type="object",
     *                         @OA\Property(property="aiTool", type="integer"),
     *                         @OA\Property(property="automation", type="integer"),
     *                         @OA\Property(property="newTech", type="integer"),
     *                         @OA\Property(property="processImprovement", type="integer")
     *                     ),
     *                     @OA\Property(property="totalTimeSaved", type="integer")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function mySummary(Request $request): JsonResponse
    {
        $startDate = $request->query('startDate') 
            ? \Carbon\Carbon::parse($request->query('startDate')) 
            : now()->startOfMonth();
        $endDate = $request->query('endDate') 
            ? \Carbon\Carbon::parse($request->query('endDate')) 
            : now()->endOfMonth();

        $summary = $this->innovationService->getUserInnovations(
            $request->user(),
            $startDate,
            $endDate
        );

        return ApiResponse::success($summary);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/innovation-logs/{id}",
     *     operationId="deleteInnovationLog",
     *     tags={"Innovation Logs"},
     *     summary="Delete an innovation log",
     *     description="Deletes an innovation log. Users can delete their own unverified logs. Verified logs require manage_innovation_logs permission.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Cannot delete verified log"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $log = InnovationLog::findOrFail($id);

        // Users can delete their own unverified logs
        if ($log->user_id !== $request->user()->id && !$request->user()->can('manage_innovation_logs')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        if ($log->is_verified && !$request->user()->can('manage_innovation_logs')) {
            return ApiResponse::error(__('messages.cannot_delete_verified'), 400);
        }

        $log->delete();

        return ApiResponse::success(null, __('messages.innovation_deleted'));
    }
}
