<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ProjectHistories\ProjectHistoryResource;
use App\Models\Project;
use App\Services\Projects\ProjectHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class ProjectHistoryController extends Controller
{
    public function __construct(
        private ProjectHistoryService $historyService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/projects/{projectId}/history",
     *     operationId="getProjectHistory",
     *     tags={"Project History"},
     *     summary="Get project change history",
     *     description="Retrieve the audit trail of changes for a specific project with optional field filtering. Requires view_project_history permission.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="projectId",
     *         in="path",
     *         required=true,
     *         description="Project ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="filter[fieldName]",
     *         in="query",
     *         description="Filter history by field name (e.g., status, budget, deadline)",
     *         @OA\Schema(type="string", example="status")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="History retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="History retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="histories",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="historyId", type="integer", example=1),
     *                         @OA\Property(property="projectId", type="integer", example=1),
     *                         @OA\Property(property="fieldName", type="string", example="status"),
     *                         @OA\Property(property="oldValue", type="string", example="draft"),
     *                         @OA\Property(property="newValue", type="string", example="active"),
     *                         @OA\Property(property="reason", type="string", example="Project kickoff approved"),
     *                         @OA\Property(property="changedBy", type="integer", example=1),
     *                         @OA\Property(
     *                             property="changedByUser",
     *                             type="object",
     *                             @OA\Property(property="userId", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="John Doe"),
     *                             @OA\Property(property="email", type="string", example="john@example.com")
     *                         ),
     *                         @OA\Property(property="createdAt", type="string", format="date-time"),
     *                         @OA\Property(property="updatedAt", type="string", format="date-time")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - No permission to view history",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to view project history.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Project not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Project not found")
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
    public function index(Request $request, Project $project): JsonResponse
    {
        // Check permission to view project history
        $user = $request->user();
        if (!$user->can('view_project_history')) {
            return ApiResponse::error(
                __('messages.no_permission_view_history'),
                403
            );
        }

        // Get optional field filter from query parameters
        $fieldFilter = $request->input('filter.fieldName');

        $histories = $this->historyService->getHistory($project, $fieldFilter);

        return ApiResponse::success(
            [
                'histories' => ProjectHistoryResource::collection($histories),
            ],
            'History retrieved successfully'
        );
    }
}
