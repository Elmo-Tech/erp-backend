<?php

namespace App\Modules\Meetings\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Meetings\Actions\DeleteMeetingAction;
use App\Modules\Meetings\Actions\ListMeetingsAction;
use App\Modules\Meetings\Actions\ScheduleMeetingAction;
use App\Modules\Meetings\Actions\UpdateMeetingAction;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Requests\CancelMeetingRequest;
use App\Modules\Meetings\Requests\CompleteMeetingRequest;
use App\Modules\Meetings\Requests\CreateMeetingRequest;
use App\Modules\Meetings\Requests\UpdateMeetingRequest;
use App\Modules\Meetings\Resources\MeetingCollection;
use App\Modules\Meetings\Resources\MeetingResource;
use App\Modules\Meetings\Services\MeetingService;
use App\Modules\Notification\Notifications\MeetingScheduledNotification;
use App\Modules\Settings\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    public function __construct(
        private MeetingService $meetingService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    public function index(Request $request, ListMeetingsAction $action): JsonResponse
    {
        return ApiResponse::success(
            new MeetingCollection($action->execute(
                (array) $request->get('filter', []),
                (int) $request->get('perPage', 15),
                $request->get('model_type'),
                $request->get('model_id') ? (int) $request->get('model_id') : null,
                $request->user(),
            )),
            __('messages.meetings_retrieved')
        );
    }

    public function store(CreateMeetingRequest $request, ScheduleMeetingAction $action): JsonResponse
    {
        $meeting = $action->execute($request->validated(), $request->user());
        $this->notifyInternalAttendees($meeting);

        return ApiResponse::success(
            new MeetingResource($meeting->load(['model', 'assignedTo'])),
            __('messages.meeting_created'),
            201
        );
    }

    public function show(Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        return ApiResponse::success(
            new MeetingResource($meeting->load(['model', 'assignedTo'])),
            __('messages.meeting_retrieved')
        );
    }

    public function update(UpdateMeetingRequest $request, Meeting $meeting, UpdateMeetingAction $action): JsonResponse
    {
        $this->authorize('update', $meeting);

        return ApiResponse::success(
            new MeetingResource($action->execute($meeting, $request->validated(), $request->user())),
            __('messages.meeting_updated')
        );
    }

    public function complete(CompleteMeetingRequest $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('complete', $meeting);

        $meeting = $this->meetingService->markAsComplete(
            $meeting,
            $request->get('minutes'),
            $request->user()
        );

        return ApiResponse::success(
            new MeetingResource($meeting->load(['model', 'assignedTo'])),
            __('messages.meeting_completed')
        );
    }

    public function cancel(CancelMeetingRequest $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('cancel', $meeting);

        $meeting = $this->meetingService->cancelMeeting(
            $meeting,
            $request->get('cancellationReason'),
            $request->user()
        );

        return ApiResponse::success(
            new MeetingResource($meeting->load(['model', 'assignedTo'])),
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

    public function destroy(Meeting $meeting, DeleteMeetingAction $action): JsonResponse
    {
        abort_if(! request()->user()?->can('delete_meetings'), 403, 'Unauthorized');
        $this->authorize('delete', $meeting);

        $action->execute($meeting);

        return ApiResponse::success(
            null,
            __('messages.meeting_deleted')
        );
    }

    private function notifyInternalAttendees(Meeting $meeting): void
    {
        $attendeeIds = collect($meeting->internal_attendees ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($attendeeIds->isEmpty()) {
            return;
        }

        User::query()
            ->whereIn('id', $attendeeIds)
            ->get()
            ->each(fn (User $user) => $user->notify(new MeetingScheduledNotification($meeting)));
    }
}
