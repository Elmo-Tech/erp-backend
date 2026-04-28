<?php

namespace App\Modules\Lead\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Lead\Actions\AddLeadActivityAction;
use App\Modules\Lead\Actions\CreateLeadAction;
use App\Modules\Lead\Actions\ScheduleLeadMeetingAction;
use App\Modules\Lead\Actions\UpdateLeadAction;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadMeeting;
use App\Modules\Lead\Requests\StoreLeadActivityRequest;
use App\Modules\Lead\Requests\StoreLeadMeetingRequest;
use App\Modules\Lead\Requests\StoreLeadRequest;
use App\Modules\Lead\Requests\UpdateLeadRequest;
use App\Modules\Lead\Requests\UpdateLeadStatusRequest;
use App\Modules\Lead\Resources\LeadActivityResource;
use App\Modules\Lead\Resources\LeadCollection;
use App\Modules\Lead\Resources\LeadMeetingResource;
use App\Modules\Lead\Resources\LeadResource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class LeadController extends Controller
{
    public function __construct(
        private CreateLeadAction $createLeadAction,
        private UpdateLeadAction $updateLeadAction,
        private AddLeadActivityAction $addLeadActivityAction,
        private ScheduleLeadMeetingAction $scheduleLeadMeetingAction
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    public function index(Request $request): JsonResponse
    {
        $leads = QueryBuilder::for(Lead::class)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('source'),
                AllowedFilter::exact('priority'),
                AllowedFilter::exact('reference_type'),
                AllowedFilter::exact('reference_id'),
                AllowedFilter::exact('assigned_to'),
                'assignedTo' => AllowedFilter::callback('assignedTo', fn ($query, $value) => $query->where('assigned_to', $value)),
                AllowedFilter::scope('needs_follow_up'),
                AllowedFilter::scope('active'),
                'search' => AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%")
                            ->orWhere('email', 'like', "%{$value}%")
                            ->orWhere('company', 'like', "%{$value}%")
                            ->orWhere('phone', 'like', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts(['created_at', 'priority', 'expected_close_date', 'expected_budget'])
            ->allowedIncludes(['activities', 'meetings', 'quotations', 'timeEntries'])
            ->defaultSort('-created_at')
            ->paginate($request->input('perPage', 15));

        return ApiResponse::success(
            new LeadCollection($leads),
            __('leads.retrieved')
        );
    }

    public function store(StoreLeadRequest $request): JsonResponse
    {
        $lead = $this->createLeadAction->execute(
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new LeadResource($lead),
            __('leads.created'),
            201
        );
    }

    public function show(Lead $lead): JsonResponse
    {
        $lead->load(['activities', 'meetings', 'quotations']);

        return ApiResponse::success(
            new LeadResource($lead),
            __('leads.retrieved')
        );
    }

    public function update(UpdateLeadRequest $request, Lead $lead): JsonResponse
    {
        $lead = $this->updateLeadAction->execute(
            $lead,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new LeadResource($lead),
            __('leads.updated')
        );
    }

    public function destroy(Lead $lead): JsonResponse
    {
        $lead->delete();

        return ApiResponse::success(null, __('leads.deleted'));
    }

    public function updateStatus(UpdateLeadStatusRequest $request, Lead $lead): JsonResponse
    {
        $validated = $request->validated();

        $lead = $this->updateLeadAction->updateStatus(
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

    public function kanban(): JsonResponse
    {
        $leads = Lead::with(['activities' => fn ($q) => $q->latest()->limit(1)])
            ->active()
            ->get()
            ->groupBy('status');

        $result = [];
        foreach (\App\Enums\LeadStatus::cases() as $status) {
            if (! in_array($status, [\App\Enums\LeadStatus::WON, \App\Enums\LeadStatus::LOST])) {
                $result[$status->value] = $leads->get($status->value, collect())->values();
            }
        }

        return ApiResponse::success($result, __('leads.kanban_retrieved'));
    }

    public function followUps(): JsonResponse
    {
        $leads = Lead::needsFollowUp()
            ->orderBy('next_follow_up_at')
            ->paginate(10);

        return ApiResponse::success(
            new LeadCollection($leads),
            __('leads.follow_ups_retrieved')
        );
    }

    public function addActivity(StoreLeadActivityRequest $request, Lead $lead): JsonResponse
    {
        $activity = $this->addLeadActivityAction->execute(
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

    public function activities(Lead $lead): JsonResponse
    {
        $activities = $lead->activities()->with('user')->paginate(20);

        return ApiResponse::success(
            LeadActivityResource::collection($activities),
            __('leads.activities_retrieved')
        );
    }

    public function scheduleMeeting(StoreLeadMeetingRequest $request, Lead $lead): JsonResponse
    {
        $meeting = $this->scheduleLeadMeetingAction->execute(
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

    public function meetings(Lead $lead): JsonResponse
    {
        $meetings = $lead->meetings()->paginate(20);

        return ApiResponse::success(
            LeadMeetingResource::collection($meetings),
            __('leads.meetings_retrieved')
        );
    }

    public function updateMeeting(Request $request, Lead $lead, LeadMeeting $meeting): JsonResponse
    {
        $meeting = $this->scheduleLeadMeetingAction->update(
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
