<?php

namespace App\Modules\HR\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\PersonalTasks\Models\PersonalTimeEntry;
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
        abort_unless($request->user()->can('view_team_pulse'), 403, 'Unauthorized');

        $activeEntries = ProjectTimeEntry::query()
            ->with(['task.project'])
            ->whereNull('end_time')
            ->latest('start_time')
            ->get()
            ->keyBy('user_id');

        $activePersonalEntries = PersonalTimeEntry::query()
            ->with('personalTask')
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
            ->map(function (User $user) use ($activeEntries, $activePersonalEntries, $attendances): array {
                /** @var Attendance|null $attendance */
                $attendance = $attendances->get($user->id);
                $isClockedIn = $attendance?->clock_in !== null && $attendance?->clock_out === null;

                /** @var ProjectTimeEntry|null $entry */
                $entry = $isClockedIn ? $activeEntries->get($user->id) : null;
                /** @var PersonalTimeEntry|null $personalEntry */
                $personalEntry = $isClockedIn && $entry === null ? $activePersonalEntries->get($user->id) : null;
                $timerStartedAt = $entry?->start_time ?? $personalEntry?->start_time;

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
                    'activeTaskId' => $entry?->task_id ?? $personalEntry?->personal_task_id,
                    'activeTaskTitle' => $entry?->task?->title ?? $personalEntry?->personalTask?->title,
                    'activeProjectId' => $entry?->task?->project_id,
                    'activeProjectTitle' => $entry?->task?->project?->title ?? ($personalEntry ? 'Personal' : null),
                    'activeTaskSource' => $entry ? 'project' : ($personalEntry ? 'personal' : null),
                    'activeTimeEntryId' => $entry?->id ?? $personalEntry?->id,
                    'timerStartedAt' => $timerStartedAt?->toISOString(),
                    'elapsedSeconds' => $timerStartedAt ? (int) $timerStartedAt->diffInSeconds(now()) : null,
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
