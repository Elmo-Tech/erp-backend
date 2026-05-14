<?php

namespace App\Modules\HR\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Project\Models\ProjectTimeEntry;
use App\Modules\Settings\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TeamPulseController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    public function __invoke(Request $request): JsonResponse
    {
        abort_if(
            ! $request->user()->can('manage_all_projects') && ! $request->user()->can('manage_users'),
            403,
            'Unauthorized'
        );

        $activeEntries = ProjectTimeEntry::query()
            ->with(['task.project'])
            ->whereNull('end_time')
            ->latest('start_time')
            ->get()
            ->keyBy('user_id');

        $attendances = Attendance::query()
            ->whereDate('date', Carbon::today())
            ->get()
            ->keyBy('user_id');

        $pulse = User::query()
            ->where('is_active', true)
            ->where('is_employee', true)
            ->whereKeyNot($request->user()->id)
            ->get(['id', 'name', 'email', 'avatar'])
            ->map(function (User $user) use ($activeEntries, $attendances): array {
                /** @var Attendance|null $attendance */
                $attendance = $attendances->get($user->id);
                $isClockedIn = $attendance?->clock_in !== null && $attendance?->clock_out === null;

                /** @var ProjectTimeEntry|null $entry */
                $entry = $isClockedIn ? $activeEntries->get($user->id) : null;

                return [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'avatar' => $user->avatar,
                    ],
                    'attendanceId' => $attendance?->id,
                    'status' => $isClockedIn ? 'clocked_in' : 'idle',
                    'clockIn' => $attendance?->clock_in?->toISOString(),
                    'activeTaskId' => $entry?->task_id,
                    'activeTaskTitle' => $entry?->task?->title,
                    'activeProjectId' => $entry?->task?->project_id,
                    'activeProjectTitle' => $entry?->task?->project?->title,
                    'activeTimeEntryId' => $entry?->id,
                    'timerStartedAt' => $entry?->start_time?->toISOString(),
                    'elapsedSeconds' => $entry?->start_time ? (int) $entry->start_time->diffInSeconds(now()) : null,
                ];
            })
            ->sortBy([
                ['status', 'asc'],
                ['clockIn', 'asc'],
                ['user.name', 'asc'],
            ])
            ->values()
            ->all();

        return ApiResponse::success($pulse, 'Team pulse retrieved successfully');
    }
}
