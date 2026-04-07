<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Trackable;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\TimeEntries\StartTimerRequest;
use App\Http\Requests\V1\TimeEntries\StopTimerRequest;
use App\Http\Resources\V1\TimeEntries\TimeEntryCollection;
use App\Http\Resources\V1\TimeEntries\TimeEntryResource;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\TimeEntry;
use App\Services\Sales\TimeTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TimeEntryController extends Controller
{
    public function __construct(
        private TimeTrackingService $timeTrackingService
    ) {
        // Apply middleware using Laravel 12 approach
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/time-entries",
     *     operationId="getTimeEntries",
     *     tags={"Time Entries"},
     *     summary="Get time entries with filtering",
     *     description="Retrieve a paginated list of time entries with optional filtering by trackable type, ID, and date range",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[trackableType]",
     *         in="query",
     *         description="Filter by trackable type",
     *
     *         @OA\Schema(type="string", enum={"App\\Models\\Lead", "App\\Models\\Quotation", "App\\Models\\Task"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[trackableId]",
     *         in="query",
     *         description="Filter by trackable ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[startDate]",
     *         in="query",
     *         description="Filter by start date (YYYY-MM-DD)",
     *
     *         @OA\Schema(type="string", format="date")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[endDate]",
     *         in="query",
     *         description="Filter by end date (YYYY-MM-DD)",
     *
     *         @OA\Schema(type="string", format="date")
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="Number of items per page",
     *
     *         @OA\Schema(type="integer", minimum=1, maximum=100, example=15)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Time entries retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Time entries retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="timeEntries",
     *                     type="array",
     *
     *                     @OA\Items(
     *
     *                         @OA\Property(property="timeEntryId", type="integer", example=1),
     *                         @OA\Property(property="userId", type="integer", example=1),
     *                         @OA\Property(property="trackableId", type="integer", example=1),
     *                         @OA\Property(property="trackableType", type="string", example="App\\Models\\Lead"),
     *                         @OA\Property(property="trackableDisplayName", type="string", example="John Doe (Acme Corp)"),
     *                         @OA\Property(property="startTime", type="string", format="date-time"),
     *                         @OA\Property(property="endTime", type="string", format="date-time"),
     *                         @OA\Property(property="durationMinutes", type="integer", example=120),
     *                         @OA\Property(property="isActive", type="boolean", example=false),
     *                         @OA\Property(property="createdAt", type="string", format="date-time"),
     *                         @OA\Property(property="updatedAt", type="string", format="date-time")
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
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $timeEntries = QueryBuilder::for(TimeEntry::class)
            ->with(['trackable', 'user'])
            ->allowedFilters([
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('trackable_type'),
                AllowedFilter::exact('trackable_id'),
                AllowedFilter::callback('start_date', function ($query, $value) {
                    $query->whereDate('start_time', '>=', $value);
                }),
                AllowedFilter::callback('end_date', function ($query, $value) {
                    $query->whereDate('start_time', '<=', $value);
                }),
            ])
            ->defaultSort('-start_time')
            ->paginate($request->get('perPage', 15));

        return ApiResponse::success(
            new TimeEntryCollection($timeEntries),
            'Time entries retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/time-entries/start",
     *     operationId="startTimer",
     *     tags={"Time Entries"},
     *     summary="Start a timer for a trackable entity",
     *     description="Start a new timer for a lead, quotation, or task",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"trackableId", "trackableType"},
     *
     *             @OA\Property(property="trackableId", type="integer", example=1),
     *             @OA\Property(property="trackableType", type="string", enum={"lead", "quotation", "task"}, example="lead")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Timer started successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Timer started successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="timeEntryId", type="integer", example=1),
     *                 @OA\Property(property="userId", type="integer", example=1),
     *                 @OA\Property(property="trackableId", type="integer", example=1),
     *                 @OA\Property(property="trackableType", type="string", example="App\\Models\\Lead"),
     *                 @OA\Property(property="trackableDisplayName", type="string", example="John Doe (Acme Corp)"),
     *                 @OA\Property(property="startTime", type="string", format="date-time"),
     *                 @OA\Property(property="endTime", type="string", nullable=true, example=null),
     *                 @OA\Property(property="durationMinutes", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="isActive", type="boolean", example=true),
     *                 @OA\Property(property="createdAt", type="string", format="date-time"),
     *                 @OA\Property(property="updatedAt", type="string", format="date-time")
     *             )
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
     *             @OA\Property(property="message", type="string", example="Validation failed for starting timer"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="trackableId",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="The trackableId field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Business logic error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User already has an active timer. Stop it before starting a new one.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Trackable entity not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Trackable entity not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function start(StartTimerRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $user = $request->user();

            // Map trackable type to model class
            $trackableClass = match ($data['trackableType']) {
                'lead' => Lead::class,
                'quotation' => Quotation::class,
                'task' => 'App\\Models\\Task', // For future implementation
                default => null
            };

            if (! $trackableClass) {
                return ApiResponse::error('Invalid trackable type', 400);
            }

            // Find the trackable entity
            $trackable = $trackableClass::find($data['trackableId']);

            if (! $trackable) {
                return ApiResponse::notFound('Trackable entity not found');
            }

            // Ensure the entity implements Trackable interface
            if (! $trackable instanceof Trackable) {
                return ApiResponse::error('Entity does not support time tracking', 400);
            }

            // Start the timer
            $timeEntry = $this->timeTrackingService->startTimer($user, $trackable);

            return ApiResponse::success(
                new TimeEntryResource($timeEntry->load('trackable')),
                'Timer started successfully',
                201
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/time-entries/stop",
     *     operationId="stopTimer",
     *     tags={"Time Entries"},
     *     summary="Stop the active timer",
     *     description="Stop the currently active timer for the authenticated user",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Timer stopped successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Timer stopped successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="timeEntryId", type="integer", example=1),
     *                 @OA\Property(property="userId", type="integer", example=1),
     *                 @OA\Property(property="trackableId", type="integer", example=1),
     *                 @OA\Property(property="trackableType", type="string", example="App\\Models\\Lead"),
     *                 @OA\Property(property="trackableDisplayName", type="string", example="John Doe (Acme Corp)"),
     *                 @OA\Property(property="startTime", type="string", format="date-time"),
     *                 @OA\Property(property="endTime", type="string", format="date-time"),
     *                 @OA\Property(property="durationMinutes", type="integer", example=120),
     *                 @OA\Property(property="isActive", type="boolean", example=false),
     *                 @OA\Property(property="createdAt", type="string", format="date-time"),
     *                 @OA\Property(property="updatedAt", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="No active timer found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No active timer found for this user.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function stop(StopTimerRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Stop the timer
            $timeEntry = $this->timeTrackingService->stopTimer($user);

            return ApiResponse::success(
                new TimeEntryResource($timeEntry->load('trackable')),
                'Timer stopped successfully'
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/time-entries/active",
     *     operationId="getActiveTimer",
     *     tags={"Time Entries"},
     *     summary="Get the current user's active timer",
     *     description="Retrieve the currently active timer for the authenticated user",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Active timer retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Active timer retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="timeEntryId", type="integer", example=1),
     *                 @OA\Property(property="userId", type="integer", example=1),
     *                 @OA\Property(property="trackableId", type="integer", example=1),
     *                 @OA\Property(property="trackableType", type="string", example="App\\Models\\Lead"),
     *                 @OA\Property(property="trackableDisplayName", type="string", example="John Doe (Acme Corp)"),
     *                 @OA\Property(property="startTime", type="string", format="date-time"),
     *                 @OA\Property(property="endTime", type="string", nullable=true, example=null),
     *                 @OA\Property(property="durationMinutes", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="isActive", type="boolean", example=true),
     *                 @OA\Property(property="createdAt", type="string", format="date-time"),
     *                 @OA\Property(property="updatedAt", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="No active timer found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="No active timer found"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function active(Request $request): JsonResponse
    {
        $user = $request->user();

        $activeTimer = $this->timeTrackingService->getActiveTimer($user);

        if (! $activeTimer) {
            return ApiResponse::success(
                null,
                'No active timer found'
            );
        }

        return ApiResponse::success(
            new TimeEntryResource($activeTimer->load('trackable')),
            'Active timer retrieved successfully'
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/time-entries/{id}",
     *     operationId="updateTimeEntry",
     *     tags={"Time Entries"},
     *     summary="Update a time entry (Admin/Super Admin only)",
     *     description="Update time entry details. Only admins and super admins can modify time entries.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Time Entry ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="startTime", type="string", format="date-time", example="2025-01-06 09:00:00"),
     *             @OA\Property(property="endTime", type="string", format="date-time", example="2025-01-06 17:00:00"),
     *             @OA\Property(property="durationMinutes", type="integer", example=480),
     *             @OA\Property(property="notes", type="string", example="Manual adjustment for missed timer")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Time entry updated successfully"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - Admin access required"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Time entry not found"
     *     )
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Check permission to edit time entries
        if (! $user->can('edit_time_entries')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $timeEntry = TimeEntry::find($id);

        if (! $timeEntry) {
            return ApiResponse::notFound(__('messages.time_entry_not_found'));
        }

        $updateData = [];

        if ($request->has('startTime')) {
            $updateData['start_time'] = $request->input('startTime');
        }

        if ($request->has('endTime')) {
            $updateData['end_time'] = $request->input('endTime');
        }

        if ($request->has('durationMinutes')) {
            $updateData['duration_minutes'] = $request->input('durationMinutes');
        }

        // If start and end time provided but no duration, calculate it
        if (isset($updateData['start_time']) && isset($updateData['end_time']) && ! isset($updateData['duration_minutes'])) {
            $start = \Carbon\Carbon::parse($updateData['start_time']);
            $end = \Carbon\Carbon::parse($updateData['end_time']);
            $updateData['duration_minutes'] = (int) floor($start->diffInSeconds($end) / 60);
        }

        $timeEntry->update($updateData);

        return ApiResponse::success(
            new TimeEntryResource($timeEntry->fresh()->load('trackable')),
            __('messages.time_entry_updated')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/time-entries/all",
     *     operationId="getAllTimeEntries",
     *     tags={"Time Entries"},
     *     summary="Get all time entries (Admin/Super Admin only)",
     *     description="Retrieve all time entries with filtering. Only admins and super admins can access.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[userId]",
     *         in="query",
     *         description="Filter by user ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[startDate]",
     *         in="query",
     *         description="Filter by start date (YYYY-MM-DD)",
     *
     *         @OA\Schema(type="string", format="date")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[endDate]",
     *         in="query",
     *         description="Filter by end date (YYYY-MM-DD)",
     *
     *         @OA\Schema(type="string", format="date")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Time entries retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - Admin access required"
     *     )
     * )
     */
    public function all(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check permission to view all time entries
        if (! $user->can('view_all_time_entries')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $timeEntries = QueryBuilder::for(TimeEntry::class)
            ->with(['trackable', 'user'])
            ->allowedFilters([
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('trackable_type'),
                AllowedFilter::exact('trackable_id'),
                AllowedFilter::callback('start_date', function ($query, $value) {
                    $query->whereDate('start_time', '>=', $value);
                }),
                AllowedFilter::callback('end_date', function ($query, $value) {
                    $query->whereDate('start_time', '<=', $value);
                }),
            ])
            ->defaultSort('-start_time')
            ->paginate($request->get('perPage', 15));

        return ApiResponse::success(
            new TimeEntryCollection($timeEntries),
            'Time entries retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/time-entries/active-timers",
     *     operationId="getActiveTimers",
     *     tags={"Time Entries"},
     *     summary="Get all active timers (Admin/Super Admin only)",
     *     description="Retrieve all currently active timers with user and trackable details. Only admins and super admins can access.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Active timers retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Active timers retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="timeEntryId", type="integer", example=1),
     *                     @OA\Property(property="userId", type="integer", example=1),
     *                     @OA\Property(property="userName", type="string", example="John Doe"),
     *                     @OA\Property(property="trackableId", type="integer", example=1),
     *                     @OA\Property(property="trackableType", type="string", example="App\\Models\\Task"),
     *                     @OA\Property(property="trackableDisplayName", type="string", example="Fix login bug"),
     *                     @OA\Property(property="startTime", type="string", format="date-time"),
     *                     @OA\Property(property="elapsedMinutes", type="integer", example=45),
     *                     @OA\Property(property="createdAt", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - Admin access required"
     *     )
     * )
     */
    public function activeTimers(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check permission to view all time entries (admin/super admin only)
        if (! $user->can('view_all_time_entries')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $activeTimers = TimeEntry::with(['user', 'trackable'])
            ->whereNull('end_time')
            ->orderBy('start_time', 'desc')
            ->get();

        $timersData = $activeTimers->map(function ($timer) {
            $elapsedMinutes = $timer->start_time->diffInMinutes(now());

            return [
                'timeEntryId' => $timer->id,
                'userId' => $timer->user_id,
                'userName' => $timer->user->name ?? 'Unknown User',
                'trackableId' => $timer->trackable_id,
                'trackableType' => $timer->trackable_type,
                'trackableDisplayName' => $timer->trackable?->getTrackableDisplayName() ?? 'Unknown',
                'startTime' => $timer->start_time->toISOString(),
                'elapsedMinutes' => $elapsedMinutes,
                'createdAt' => $timer->created_at->toISOString(),
            ];
        });

        return ApiResponse::success(
            $timersData,
            'Active timers retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/time-entries/{id}/stop-admin",
     *     operationId="stopTimerAdmin",
     *     tags={"Time Entries"},
     *     summary="Stop a timer as admin (Admin/Super Admin only)",
     *     description="Stop any active timer as an admin. Only admins and super admins can access.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Time Entry ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Timer stopped successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Timer stopped successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - Admin access required"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Time entry not found or already stopped"
     *     )
     * )
     */
    public function stopTimerAdmin(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Check permission to manage time entries (admin/super admin only)
        if (! $user->can('edit_time_entries')) {
            return ApiResponse::error(__('messages.unauthorized'), 403);
        }

        $timeEntry = TimeEntry::with(['user', 'trackable'])->find($id);

        if (! $timeEntry) {
            return ApiResponse::notFound('Time entry not found');
        }

        if ($timeEntry->end_time !== null) {
            return ApiResponse::error('Timer is already stopped', 400);
        }

        // Stop the timer using the service
        $timerUser = $timeEntry->user;
        $stoppedTimer = $this->timeTrackingService->stopTimer($timerUser);

        return ApiResponse::success(
            [
                'timeEntryId' => $stoppedTimer->id,
                'userName' => $timerUser->name,
                'trackableDisplayName' => $stoppedTimer->trackable?->getTrackableDisplayName() ?? 'Unknown',
                'durationMinutes' => $stoppedTimer->duration_minutes,
            ],
            'Timer stopped successfully'
        );
    }
}
