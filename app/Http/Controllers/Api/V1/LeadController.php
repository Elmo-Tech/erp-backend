<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Leads\StoreLeadActivityRequest;
use App\Http\Requests\V1\Leads\StoreLeadMeetingRequest;
use App\Http\Requests\V1\Leads\StoreLeadRequest;
use App\Http\Requests\V1\Leads\UpdateLeadRequest;
use App\Http\Requests\V1\Leads\UpdateLeadStatusRequest;
use App\Http\Resources\V1\Leads\LeadActivityResource;
use App\Http\Resources\V1\Leads\LeadCollection;
use App\Http\Resources\V1\Leads\LeadMeetingResource;
use App\Http\Resources\V1\Leads\LeadResource;
use App\Models\Lead;
use App\Models\LeadMeeting;
use App\Services\Sales\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class LeadController extends Controller
{
    public function __construct(
        private LeadService $leadService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/leads",
     *     operationId="getLeads",
     *     tags={"Leads"},
     *     summary="Get all leads with pagination and filtering",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="filter[status]", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="filter[source]", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="filter[priority]", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="filter[search]", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="include", in="query", description="activities,meetings,quotations", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="perPage", in="query", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Leads retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $leads = $this->leadService->getLeads($request->all());

        return ApiResponse::success(
            new LeadCollection($leads),
            __('leads.retrieved')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/leads",
     *     operationId="createLead",
     *     tags={"Leads"},
     *     summary="Create a new lead",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"name", "email"},
     *
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="company", type="string"),
     *             @OA\Property(property="country", type="string"),
     *             @OA\Property(property="source", type="string"),
     *             @OA\Property(property="referenceType", type="string", enum={"sales_rep", "client_referral", "employee"}),
     *             @OA\Property(property="referenceId", type="integer"),
     *             @OA\Property(property="priority", type="string", enum={"low", "medium", "high", "urgent"}),
     *             @OA\Property(property="expectedBudget", type="number"),
     *             @OA\Property(property="expectedCloseDate", type="string", format="date"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Lead created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreLeadRequest $request): JsonResponse
    {
        $lead = $this->leadService->createLead(
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new LeadResource($lead),
            __('leads.created'),
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/leads/{id}",
     *     operationId="getLead",
     *     tags={"Leads"},
     *     summary="Get a single lead",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Lead retrieved successfully"),
     *     @OA\Response(response=404, description="Lead not found")
     * )
     */
    public function show(Lead $lead): JsonResponse
    {
        $lead->load(['activities', 'meetings', 'quotations']);

        return ApiResponse::success(
            new LeadResource($lead),
            __('leads.retrieved')
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/leads/{id}",
     *     operationId="updateLead",
     *     tags={"Leads"},
     *     summary="Update an existing lead",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *
     *     @OA\Response(response=200, description="Lead updated successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateLeadRequest $request, Lead $lead): JsonResponse
    {
        $lead = $this->leadService->updateLead(
            $lead,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new LeadResource($lead),
            __('leads.updated')
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/leads/{id}",
     *     operationId="deleteLead",
     *     tags={"Leads"},
     *     summary="Delete a lead",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Lead deleted successfully"),
     *     @OA\Response(response=404, description="Lead not found")
     * )
     */
    public function destroy(Lead $lead): JsonResponse
    {
        $lead->delete();

        return ApiResponse::success(null, __('leads.deleted'));
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/leads/{id}/status",
     *     operationId="updateLeadStatus",
     *     tags={"Leads"},
     *     summary="Update lead status",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"status"},
     *
     *             @OA\Property(property="status", type="string"),
     *             @OA\Property(property="lossReason", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Status updated successfully")
     * )
     */
    public function updateStatus(UpdateLeadStatusRequest $request, Lead $lead): JsonResponse
    {
        $validated = $request->validated();

        $lead = $this->leadService->updateStatus(
            $lead,
            $validated['status'],
            $request->user(),
            $validated['lossReason'] ?? null,
            $validated['meetingId'] ?? null,
            $validated['outcome'] ?? null,
            $validated['feedback'] ?? null,
            $validated['teamLeadId'] ?? null
        );

        return ApiResponse::success(
            new LeadResource($lead),
            __('leads.status_updated')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/leads/kanban",
     *     operationId="getLeadsKanban",
     *     tags={"Leads"},
     *     summary="Get leads grouped by status for Kanban view",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Kanban data retrieved successfully")
     * )
     */
    public function kanban(): JsonResponse
    {
        $data = $this->leadService->getLeadsByStatus();

        return ApiResponse::success($data, __('leads.kanban_retrieved'));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/leads/follow-ups",
     *     operationId="getLeadsNeedingFollowUp",
     *     tags={"Leads"},
     *     summary="Get leads needing follow-up",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Follow-ups retrieved successfully")
     * )
     */
    public function followUps(): JsonResponse
    {
        $leads = $this->leadService->getLeadsNeedingFollowUp();

        return ApiResponse::success(
            new LeadCollection($leads),
            __('leads.follow_ups_retrieved')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/leads/{id}/activities",
     *     operationId="addLeadActivity",
     *     tags={"Leads"},
     *     summary="Add activity to lead",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"type", "title"},
     *
     *             @OA\Property(property="type", type="string", enum={"call", "email", "meeting", "note", "status_change"}),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="outcome", type="string"),
     *             @OA\Property(property="scheduledAt", type="string", format="date-time"),
     *             @OA\Property(property="completedAt", type="string", format="date-time")
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Activity added successfully")
     * )
     */
    public function addActivity(StoreLeadActivityRequest $request, Lead $lead): JsonResponse
    {
        $activity = $this->leadService->addActivity(
            $lead,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new LeadActivityResource($activity),
            __('leads.activity_added'),
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/leads/{id}/activities",
     *     operationId="getLeadActivities",
     *     tags={"Leads"},
     *     summary="Get lead activities",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Activities retrieved successfully")
     * )
     */
    public function activities(Lead $lead): JsonResponse
    {
        $activities = $lead->activities()->with('user')->paginate(20);

        return ApiResponse::success(
            LeadActivityResource::collection($activities),
            __('leads.activities_retrieved')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/leads/{id}/meetings",
     *     operationId="scheduleLeadMeeting",
     *     tags={"Leads"},
     *     summary="Schedule a meeting for lead",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"title", "location", "scheduledAt"},
     *
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="location", type="string", enum={"online", "office", "client_site"}),
     *             @OA\Property(property="meetingLink", type="string"),
     *             @OA\Property(property="scheduledAt", type="string", format="date-time"),
     *             @OA\Property(property="durationMinutes", type="integer"),
     *             @OA\Property(property="attendees", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Meeting scheduled successfully")
     * )
     */
    public function scheduleMeeting(StoreLeadMeetingRequest $request, Lead $lead): JsonResponse
    {
        $meeting = $this->leadService->scheduleMeeting(
            $lead,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new LeadMeetingResource($meeting),
            __('leads.meeting_scheduled'),
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/leads/{id}/meetings",
     *     operationId="getLeadMeetings",
     *     tags={"Leads"},
     *     summary="Get lead meetings",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Meetings retrieved successfully")
     * )
     */
    public function meetings(Lead $lead): JsonResponse
    {
        $meetings = $lead->meetings()->paginate(20);

        return ApiResponse::success(
            LeadMeetingResource::collection($meetings),
            __('leads.meetings_retrieved')
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/leads/{leadId}/meetings/{meetingId}",
     *     operationId="updateLeadMeeting",
     *     tags={"Leads"},
     *     summary="Update a meeting",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Meeting updated successfully")
     * )
     */
    public function updateMeeting(Request $request, Lead $lead, LeadMeeting $meeting): JsonResponse
    {
        $meeting = $this->leadService->updateMeeting(
            $meeting,
            $request->all(),
            $request->user()
        );

        return ApiResponse::success(
            new LeadMeetingResource($meeting),
            __('leads.meeting_updated')
        );
    }
}
