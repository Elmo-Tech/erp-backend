<?php

namespace App\Modules\Meetings\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Client\Services\ClientActivityLogger;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Requests\CancelMeetingRequest;
use App\Modules\Meetings\Requests\CompleteMeetingRequest;
use App\Modules\Meetings\Requests\CreateMeetingRequest;
use App\Modules\Meetings\Requests\UpdateMeetingRequest;
use App\Modules\Meetings\Resources\MeetingCollection;
use App\Modules\Meetings\Resources\MeetingResource;
use App\Modules\Meetings\Services\MeetingService;
use App\Modules\Client\Models\Client;
use App\Modules\Project\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    public function __construct(
        private MeetingService $meetingService,
        private ClientActivityLogger $clientActivityLogger
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    public function index(Request $request): JsonResponse
    {
        $meetings = Meeting::with(['subject', 'assignedTo'])
            ->when($request->get('filter.status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->get('filter.assigned_to'), fn ($q, $userId) => $q->where('assigned_to', $userId))
            ->orderBy('scheduled_at', 'desc')
            ->paginate($request->get('perPage', 15));

        return ApiResponse::success(
            new MeetingCollection($meetings),
            __('messages.meetings_retrieved')
        );
    }

    public function store(CreateMeetingRequest $request): JsonResponse
    {
        $data = $request->validated();
        $subjectClass = $data['subjectType'];
        $subject = $subjectClass::findOrFail($data['subjectId']);

        $meeting = $this->meetingService->scheduleMeeting($subject, $data, $request->user());
        $this->logClientMeetingActivity($meeting, $subject, $request);

        return ApiResponse::success(
            new MeetingResource($meeting->load(['subject', 'assignedTo'])),
            __('messages.meeting_created'),
            201
        );
    }

    private function logClientMeetingActivity(Meeting $meeting, mixed $subject, Request $request): void
    {
        $client = null;

        if ($subject instanceof Client) {
            $client = $subject;
        }

        if ($subject instanceof Project) {
            $client = $subject->relationLoaded('client') ? $subject->client : $subject->client()->first();
        }

        if ($client) {
            $this->clientActivityLogger->meetingScheduled($meeting, $client, $request->user());
        }
    }

    public function show(Meeting $meeting): JsonResponse
    {
        return ApiResponse::success(
            new MeetingResource($meeting->load(['subject', 'assignedTo'])),
            __('messages.meeting_retrieved')
        );
    }

    public function update(UpdateMeetingRequest $request, Meeting $meeting): JsonResponse
    {
        $data = $request->validated();

        $meeting->update([
            'title' => $data['title'] ?? $meeting->title,
            'description' => $data['description'] ?? $meeting->description,
            'assigned_to' => $data['assignedTo'] ?? $meeting->assigned_to,
            'scheduled_at' => $data['scheduledAt'] ?? $meeting->scheduled_at,
            'outcome_summary' => $data['outcomeSummary'] ?? $meeting->outcome_summary,
            'updated_by' => $request->user()->id,
        ]);

        return ApiResponse::success(
            new MeetingResource($meeting->fresh(['subject', 'assignedTo'])),
            __('messages.meeting_updated')
        );
    }

    public function complete(CompleteMeetingRequest $request, Meeting $meeting): JsonResponse
    {
        $meeting = $this->meetingService->markAsComplete(
            $meeting,
            $request->get('minutes'),
            $request->user()
        );

        return ApiResponse::success(
            new MeetingResource($meeting->load(['subject', 'assignedTo'])),
            __('messages.meeting_completed')
        );
    }

    public function cancel(CancelMeetingRequest $request, Meeting $meeting): JsonResponse
    {
        $meeting = $this->meetingService->cancelMeeting(
            $meeting,
            $request->get('cancellationReason'),
            $request->user()
        );

        return ApiResponse::success(
            new MeetingResource($meeting->load(['subject', 'assignedTo'])),
            __('messages.meeting_cancelled')
        );
    }

    public function todaySchedule(Request $request): JsonResponse
    {
        $meetings = $this->meetingService->getTodaySchedule($request->user());

        return ApiResponse::success(
            MeetingResource::collection($meetings),
            __('messages.today_schedule_retrieved')
        );
    }

    public function upcomingSchedule(Request $request): JsonResponse
    {
        $hours = $request->get('hours', 24);
        $meetings = $this->meetingService->getUpcomingMeetings($request->user(), $hours);

        return ApiResponse::success(
            MeetingResource::collection($meetings),
            __('messages.upcoming_schedule_retrieved')
        );
    }

    public function destroy(Meeting $meeting): JsonResponse
    {
        $meeting->delete();

        return ApiResponse::success(
            null,
            __('messages.meeting_deleted')
        );
    }
}
