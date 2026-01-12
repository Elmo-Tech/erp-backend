<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Activities\CancelActivityRequest;
use App\Http\Requests\V1\Activities\CancelMeetingRequest;
use App\Http\Requests\V1\Activities\CompleteActivityRequest;
use App\Http\Requests\V1\Activities\CompleteMeetingRequest;
use App\Http\Resources\V1\Leads\LeadActivityResource;
use App\Http\Resources\V1\Leads\LeadMeetingResource;
use App\Models\LeadActivity;
use App\Models\LeadMeeting;
use App\Services\Sales\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Activities",
 *     description="Activity and Meeting management endpoints"
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
     *     operationId="getActivities",
     *     tags={"Activities"},
     *     summary="Get all activities with pagination and filtering",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="filter[status]", in="query", @OA\Schema(type="string", enum={"scheduled", "completed", "cancelled"})),
     *     @OA\Parameter(name="filter[type]", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="filter[leadId]", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="perPage", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Activities retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $activities = $this->activityService->getActivities($request->all());

        return ApiResponse::success(
            LeadActivityResource::collection($activities),
            __('activities.retrieved')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/activities/{id}/complete",
     *     operationId="completeActivity",
     *     tags={"Activities"},
     *     summary="Complete an activity with outcome",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"outcome"},
     *             @OA\Property(property="outcome", type="string", description="Meeting minutes or activity outcome"),
     *             @OA\Property(property="nextAction", type="string", description="Next action to take"),
     *             @OA\Property(property="reminderAt", type="string", format="date-time", description="Reminder date for next action")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Activity completed successfully"),
     *     @OA\Response(response=404, description="Activity not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function complete(CompleteActivityRequest $request, LeadActivity $activity): JsonResponse
    {
        $activity = $this->activityService->completeActivity(
            $activity,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new LeadActivityResource($activity),
            __('activities.completed')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/activities/{id}/cancel",
     *     operationId="cancelActivity",
     *     tags={"Activities"},
     *     summary="Cancel an activity",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"cancellationReason"},
     *             @OA\Property(property="cancellationReason", type="string", description="Reason for cancellation")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Activity cancelled successfully"),
     *     @OA\Response(response=404, description="Activity not found")
     * )
     */
    public function cancel(CancelActivityRequest $request, LeadActivity $activity): JsonResponse
    {
        $activity = $this->activityService->cancelActivity(
            $activity,
            $request->validated()['cancellationReason'],
            $request->user()
        );

        return ApiResponse::success(
            new LeadActivityResource($activity),
            __('activities.cancelled')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/meetings",
     *     operationId="getMeetings",
     *     tags={"Activities"},
     *     summary="Get all meetings with pagination and filtering",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="filter[status]", in="query", @OA\Schema(type="string", enum={"scheduled", "completed", "cancelled"})),
     *     @OA\Parameter(name="filter[leadId]", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="perPage", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Meetings retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function meetings(Request $request): JsonResponse
    {
        $meetings = $this->activityService->getMeetings($request->all());

        return ApiResponse::success(
            LeadMeetingResource::collection($meetings),
            __('meetings.retrieved')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/meetings/{id}/complete",
     *     operationId="completeMeeting",
     *     tags={"Activities"},
     *     summary="Complete a meeting with minutes",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"minutes"},
     *             @OA\Property(property="minutes", type="string", description="Meeting minutes"),
     *             @OA\Property(property="outcome", type="string", description="Meeting outcome"),
     *             @OA\Property(property="attachments", type="array", @OA\Items(type="string"), description="Attachment file paths")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Meeting completed successfully"),
     *     @OA\Response(response=404, description="Meeting not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function completeMeeting(CompleteMeetingRequest $request, LeadMeeting $meeting): JsonResponse
    {
        $meeting = $this->activityService->completeMeeting(
            $meeting,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new LeadMeetingResource($meeting),
            __('meetings.completed')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/meetings/{id}/cancel",
     *     operationId="cancelMeeting",
     *     tags={"Activities"},
     *     summary="Cancel a meeting",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"cancellationReason"},
     *             @OA\Property(property="cancellationReason", type="string", description="Reason for cancellation")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Meeting cancelled successfully"),
     *     @OA\Response(response=404, description="Meeting not found")
     * )
     */
    public function cancelMeeting(CancelMeetingRequest $request, LeadMeeting $meeting): JsonResponse
    {
        $meeting = $this->activityService->cancelMeeting(
            $meeting,
            $request->validated()['cancellationReason'],
            $request->user()
        );

        return ApiResponse::success(
            new LeadMeetingResource($meeting),
            __('meetings.cancelled')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/schedule/today",
     *     operationId="getTodaySchedule",
     *     tags={"Activities"},
     *     summary="Get today's schedule for current user",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Response(response=200, description="Today's schedule retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function todaySchedule(Request $request): JsonResponse
    {
        $schedule = $this->activityService->getTodaySchedule($request->user());

        return ApiResponse::success([
            'activities' => LeadActivityResource::collection($schedule['activities']),
            'meetings' => LeadMeetingResource::collection($schedule['meetings']),
        ], __('activities.schedule_retrieved'));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/schedule/upcoming",
     *     operationId="getUpcomingSchedule",
     *     tags={"Activities"},
     *     summary="Get upcoming events for current user",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="days", in="query", @OA\Schema(type="integer", default=7)),
     *     @OA\Response(response=200, description="Upcoming events retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function upcomingSchedule(Request $request): JsonResponse
    {
        $days = $request->input('days', 7);
        $schedule = $this->activityService->getUpcomingEvents($request->user(), $days);

        return ApiResponse::success([
            'activities' => LeadActivityResource::collection($schedule['activities']),
            'meetings' => LeadMeetingResource::collection($schedule['meetings']),
        ], __('activities.upcoming_retrieved'));
    }
}
