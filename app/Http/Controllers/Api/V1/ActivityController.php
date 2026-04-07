<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Activity\CreateActivityRequest;
use App\Http\Requests\V1\Activity\UpdateActivityRequest;
use App\Http\Resources\V1\Activity\ActivityCollection;
use App\Http\Resources\V1\Activity\ActivityResource;
use App\Models\Activity;
use App\Services\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Activities",
 *     description="Activity management endpoints"
 * )
 */
class ActivityController extends Controller
{
    public function __construct(
        private ActivityService $activityService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/activities",
     *     summary="Get paginated activities",
     *     description="Retrieve paginated list of activities with filtering options",
     *     tags={"Activities"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[type]",
     *         in="query",
     *         description="Filter by activity type",
     *
     *         @OA\Schema(type="string", enum={"call", "email", "meeting", "note", "status_change", "technical_discovery"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[status]",
     *         in="query",
     *         description="Filter by activity status",
     *
     *         @OA\Schema(type="string", enum={"scheduled", "completed", "cancelled"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[assigned_to]",
     *         in="query",
     *         description="Filter by assigned user ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="Items per page",
     *
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Activities retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Activities retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="activities",
     *                     type="array",
     *
     *                     @OA\Items(ref="#/components/schemas/ActivityResource")
     *                 ),
     *
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
        $activities = Activity::with(['subject', 'assignedTo'])
            ->when($request->get('filter.type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->get('filter.status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->get('filter.assigned_to'), fn ($q, $userId) => $q->where('assigned_to', $userId))
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('perPage', 15));

        return ApiResponse::success(
            new ActivityCollection($activities),
            __('messages.activities_retrieved')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/activities",
     *     summary="Create new activity",
     *     description="Create a new activity for any subject (Lead, Project, etc.)",
     *     tags={"Activities"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"subjectType", "subjectId", "type", "title"},
     *
     *             @OA\Property(property="subjectType", type="string", example="App\\Models\\Lead", description="Subject model class"),
     *             @OA\Property(property="subjectId", type="integer", example=1, description="Subject ID"),
     *             @OA\Property(property="type", type="string", enum={"call", "email", "meeting", "note", "technical_discovery"}, example="meeting"),
     *             @OA\Property(property="title", type="string", example="Technical Discovery Meeting"),
     *             @OA\Property(property="description", type="string", example="Discuss technical requirements"),
     *             @OA\Property(property="assignedTo", type="integer", example=2, description="User ID to assign activity to"),
     *             @OA\Property(property="scheduledAt", type="string", format="date-time", example="2026-02-01T14:00:00Z"),
     *             @OA\Property(property="outcomeSummary", type="string", example="Meeting completed successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Activity created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Activity created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ActivityResource")
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
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function store(CreateActivityRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Get the subject model
        $subjectClass = $data['subjectType'];
        $subject = $subjectClass::findOrFail($data['subjectId']);

        $activity = $this->activityService->logActivity($subject, $data, $request->user());

        return ApiResponse::success(
            new ActivityResource($activity->load(['subject', 'assignedTo'])),
            __('messages.activity_created'),
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/activities/{id}",
     *     summary="Get activity details",
     *     description="Retrieve detailed information about a specific activity",
     *     tags={"Activities"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Activity retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Activity retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ActivityResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Activity not found"
     *     )
     * )
     */
    public function show(Activity $activity): JsonResponse
    {
        return ApiResponse::success(
            new ActivityResource($activity->load(['subject', 'assignedTo'])),
            __('messages.activity_retrieved')
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/activities/{id}",
     *     summary="Update activity",
     *     description="Update an existing activity",
     *     tags={"Activities"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="title", type="string", example="Updated Meeting Title"),
     *             @OA\Property(property="description", type="string", example="Updated description"),
     *             @OA\Property(property="assignedTo", type="integer", example=3),
     *             @OA\Property(property="scheduledAt", type="string", format="date-time"),
     *             @OA\Property(property="outcomeSummary", type="string", example="Meeting outcome updated")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Activity updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Activity updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ActivityResource")
     *         )
     *     )
     * )
     */
    public function update(UpdateActivityRequest $request, Activity $activity): JsonResponse
    {
        $data = $request->validated();

        $activity->update([
            'title' => $data['title'] ?? $activity->title,
            'description' => $data['description'] ?? $activity->description,
            'assigned_to' => $data['assignedTo'] ?? $activity->assigned_to,
            'scheduled_at' => $data['scheduledAt'] ?? $activity->scheduled_at,
            'outcome_summary' => $data['outcomeSummary'] ?? $activity->outcome_summary,
            'updated_by' => $request->user()->id,
        ]);

        return ApiResponse::success(
            new ActivityResource($activity->fresh(['subject', 'assignedTo'])),
            __('messages.activity_updated')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/activities/{id}/complete",
     *     summary="Mark activity as complete",
     *     description="Mark an activity as completed with outcome summary",
     *     tags={"Activities"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"outcomeSummary"},
     *
     *             @OA\Property(property="outcomeSummary", type="string", example="Meeting completed successfully with all requirements discussed")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Activity marked as complete",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Activity marked as complete"),
     *             @OA\Property(property="data", ref="#/components/schemas/ActivityResource")
     *         )
     *     )
     * )
     */
    public function complete(Request $request, Activity $activity): JsonResponse
    {
        $request->validate([
            'outcomeSummary' => 'required|string|max:1000',
        ]);

        $activity = $this->activityService->markAsComplete(
            $activity,
            $request->get('outcomeSummary'),
            $request->user()
        );

        return ApiResponse::success(
            new ActivityResource($activity->load(['subject', 'assignedTo'])),
            __('messages.activity_completed')
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/activities/{id}",
     *     summary="Delete activity",
     *     description="Delete an activity",
     *     tags={"Activities"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Activity deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Activity deleted successfully")
     *         )
     *     )
     * )
     */
    public function destroy(Activity $activity): JsonResponse
    {
        $activity->delete();

        return ApiResponse::success(
            null,
            __('messages.activity_deleted')
        );
    }
}
