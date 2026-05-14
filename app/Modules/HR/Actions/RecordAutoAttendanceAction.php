<?php

namespace App\Modules\HR\Actions;

use App\Modules\Attendance\Models\Attendance;
use App\Modules\HR\Services\AttendanceService;
use App\Modules\Settings\Models\User;

/**
 * Backward-compatible wrapper around AttendanceService.
 */
class RecordAutoAttendanceAction
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    /**
     * @param  User  $user  The authenticated user starting a task timer.
     * @return Attendance   The existing or newly created attendance record.
     */
    public function execute(User $user): Attendance
    {
        return $this->attendanceService->checkInForToday($user);
    }
}
