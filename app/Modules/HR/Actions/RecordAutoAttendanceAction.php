<?php

namespace App\Modules\HR\Actions;

use App\Modules\Attendance\Models\Attendance;
use App\Modules\HR\Models\EmployeeProfile;
use App\Modules\Settings\Models\User;
use Illuminate\Support\Carbon;

/**
 * Auto Check-in Action — Phase 3: HR & Payroll Engine
 *
 * Triggered when a user starts their first task timer of the day.
 * Idempotent: if an attendance record already exists for today, it is
 * returned unchanged so the timer toggle is never blocked.
 *
 * Status logic:
 *   - Fetch the user's EmployeeProfile → Shift.
 *   - Deadline = shift.start_time + shift.grace_period_minutes (today).
 *   - now() > deadline  →  status = 'late'
 *   - now() <= deadline →  status = 'present'
 *   - No shift assigned →  status = 'present' (no shift = no lateness rule)
 */
class RecordAutoAttendanceAction
{
    /**
     * @param  User  $user  The authenticated user starting a task timer.
     * @return Attendance   The existing or newly created attendance record.
     */
    public function execute(User $user): Attendance
    {
        $today = Carbon::today()->toDateString();

        // ── Idempotency guard ─────────────────────────────────────────────────
        // If a record already exists for today, return it immediately.
        // This covers: user starts multiple timers in the same day.
        $existing = Attendance::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        // ── Resolve shift & lateness ──────────────────────────────────────────
        $now    = Carbon::now();
        $status = 'present';

        /** @var EmployeeProfile|null $profile */
        $profile = EmployeeProfile::with('shift')
            ->where('user_id', $user->id)
            ->first();

        $shift              = $profile?->shift;
        $expectedClockIn    = null;
        $expectedClockOut   = null;

        if ($shift !== null) {
            $expectedClockIn  = $shift->start_time;   // "HH:MM:SS"
            $expectedClockOut = $shift->end_time;

            // Compare now() against the lateness deadline for today
            if ($now->gt($shift->latenessDeadlineToday())) {
                $status = 'late';
            }
        }

        // ── Create attendance record ──────────────────────────────────────────
        return Attendance::create([
            'user_id'            => $user->id,
            'date'               => $today,
            'clock_in'           => $now,
            'clock_out'          => null,
            'status'             => $status,
            'total_worked_minutes' => 0,
            'late_minutes'       => 0,
            'is_late'            => $status === 'late',
            'idle_minutes'       => 0,
            'deduction_minutes'  => 0,
            'used_grace_day'     => false,
            'expected_clock_in'  => $expectedClockIn,
            'expected_clock_out' => $expectedClockOut,
        ]);
    }
}
