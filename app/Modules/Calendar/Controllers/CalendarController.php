<?php

namespace App\Modules\Calendar\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Calendar\Models\CalendarEvent;
use App\Modules\Calendar\Requests\StoreCalendarEventRequest;
use App\Modules\Calendar\Requests\UpdateCalendarEventRequest;
use App\Modules\Calendar\Resources\CalendarEventResource;
use App\Modules\Calendar\Services\MasterCalendarAggregatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendarController extends Controller
{
    public function __construct(
        private readonly MasterCalendarAggregatorService $aggregator,
    ) {}

    /**
     * GET /calendar
     *
     * Returns a unified calendar feed aggregated from Events, ProjectTasks,
     * and FinanceInvoices within the requested date range.
     *
     * Query params:
     *   startDate (required) — YYYY-MM-DD
     *   endDate   (required) — YYYY-MM-DD
     */
    public function index(Request $request): JsonResponse
    {
        abort_if(! $request->user()->can('view_calendar'), 403, 'Unauthorized');

        $request->validate([
            'startDate' => ['required', 'date'],
            'endDate'   => ['required', 'date', 'after_or_equal:startDate'],
        ]);

        $startDate = Carbon::parse($request->input('startDate'))->startOfDay();
        $endDate   = Carbon::parse($request->input('endDate'))->endOfDay();

        $items = $this->aggregator->aggregate($startDate, $endDate, $request->user());

        return ApiResponse::success(
            ['items' => $items, 'total' => $items->count()],
            'Calendar items retrieved successfully'
        );
    }

    /**
     * POST /calendar/events
     */
    public function store(StoreCalendarEventRequest $request): JsonResponse
    {
        abort_if(! $request->user()->can('view_calendar'), 403, 'Unauthorized');

        $data = $request->validated();

        $event = CalendarEvent::create([
            'user_id'     => $request->user()->id,
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'start_time'  => $data['startTime'],
            'end_time'    => $data['endTime'],
            'event_type'  => $data['eventType'] ?? 'custom',
        ]);

        return ApiResponse::success(
            new CalendarEventResource($event->load('creator')),
            'Event created successfully',
            201
        );
    }

    /**
     * GET /calendar/events/{event}
     */
    public function show(Request $request, CalendarEvent $event): JsonResponse
    {
        abort_if(! $request->user()->can('view_calendar'), 403, 'Unauthorized');

        $event->load('creator');

        return ApiResponse::success(
            new CalendarEventResource($event),
            'Event retrieved successfully'
        );
    }

    /**
     * PUT /calendar/events/{event}
     */
    public function update(UpdateCalendarEventRequest $request, CalendarEvent $event): JsonResponse
    {
        abort_if(! $request->user()->can('view_calendar'), 403, 'Unauthorized');

        $data = $request->validated();

        $event->update(array_filter([
            'title'       => $data['title'] ?? null,
            'description' => array_key_exists('description', $data) ? $data['description'] : $event->description,
            'start_time'  => $data['startTime'] ?? null,
            'end_time'    => $data['endTime'] ?? null,
            'event_type'  => $data['eventType'] ?? null,
        ], fn ($v) => $v !== null));

        return ApiResponse::success(
            new CalendarEventResource($event->fresh('creator')),
            'Event updated successfully'
        );
    }

    /**
     * DELETE /calendar/events/{event}
     */
    public function destroy(Request $request, CalendarEvent $event): JsonResponse
    {
        abort_if(! $request->user()->can('view_calendar'), 403, 'Unauthorized');

        $event->delete();

        return ApiResponse::success(null, 'Event deleted successfully');
    }
}
