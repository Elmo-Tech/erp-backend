<?php

namespace App\Services\Sales;

use App\Contracts\Trackable;
use App\Models\Attendance;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use RuntimeException;

class TimeTrackingService
{
    /**
     * Start a timer for a trackable entity.
     * Creates attendance record if first timer of the day.
     *
     * @param User $user
     * @param Trackable $trackable
     * @return TimeEntry
     * @throws RuntimeException
     */
    public function startTimer(User $user, Trackable $trackable): TimeEntry
    {
        // Check if user already has an active timer
        $activeTimer = $this->getActiveTimer($user);
        if ($activeTimer) {
            throw new RuntimeException('User already has an active timer. Stop it before starting a new one.');
        }

        // Smart Attendance: Create attendance if first timer of day
        $this->ensureAttendanceRecord($user);

        return TimeEntry::create([
            'user_id' => $user->id,
            'trackable_id' => $trackable->getKey(),
            'trackable_type' => get_class($trackable),
            'start_time' => Carbon::now(),
        ]);
    }

    /**
     * Stop the active timer for a user.
     *
     * @param User $user
     * @return TimeEntry
     * @throws RuntimeException
     */
    public function stopTimer(User $user): TimeEntry
    {
        $activeTimer = $this->getActiveTimer($user);

        if (!$activeTimer) {
            throw new RuntimeException('No active timer found for this user.');
        }

        $endTime = Carbon::now();
        // Calculate duration in minutes, rounded down as per Requirements 5.4, 5.5
        $durationMinutes = (int) floor($activeTimer->start_time->diffInSeconds($endTime) / 60);

        $activeTimer->update([
            'end_time' => $endTime,
            'duration_minutes' => $durationMinutes,
        ]);

        return $activeTimer->fresh();
    }

    /**
     * Get the active timer for a user.
     *
     * @param User $user
     * @return TimeEntry|null
     */
    public function getActiveTimer(User $user): ?TimeEntry
    {
        return TimeEntry::where('user_id', $user->id)
            ->whereNull('end_time')
            ->first();
    }

    /**
     * Get time entries for a specific trackable entity.
     *
     * @param Trackable $trackable
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTimeEntriesForTrackable(Trackable $trackable)
    {
        return TimeEntry::where('trackable_id', $trackable->getKey())
            ->where('trackable_type', get_class($trackable))
            ->orderBy('start_time', 'desc')
            ->get();
    }

    /**
     * Get total time spent on a trackable entity in minutes.
     *
     * @param Trackable $trackable
     * @return int
     */
    public function getTotalTimeForTrackable(Trackable $trackable): int
    {
        return TimeEntry::where('trackable_id', $trackable->getKey())
            ->where('trackable_type', get_class($trackable))
            ->whereNotNull('end_time')
            ->sum('duration_minutes');
    }

    /**
     * Ensure attendance record exists for today.
     * Creates a new attendance record with clock_in if not exists.
     *
     * @param User $user
     * @return Attendance
     */
    private function ensureAttendanceRecord(User $user): Attendance
    {
        $today = Carbon::today();

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if (!$attendance) {
            $attendance = Attendance::create([
                'user_id' => $user->id,
                'date' => $today,
                'clock_in' => Carbon::now(),
            ]);
        }

        return $attendance;
    }

    /**
     * Clock out and calculate idle time for a user.
     *
     * @param User $user
     * @return Attendance
     * @throws ModelNotFoundException
     */
    public function clockOut(User $user): Attendance
    {
        $today = Carbon::today();

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->firstOrFail();

        $clockOut = Carbon::now();
        $totalMinutes = $attendance->clock_in->diffInMinutes($clockOut);

        // Sum all completed time entries for today
        $workedMinutes = TimeEntry::where('user_id', $user->id)
            ->whereDate('start_time', $today)
            ->whereNotNull('end_time')
            ->sum('duration_minutes');

        // Calculate idle minutes, ensuring non-negative value
        $idleMinutes = max(0, $totalMinutes - $workedMinutes);

        $attendance->update([
            'clock_out' => $clockOut,
            'idle_minutes' => $idleMinutes,
        ]);

        return $attendance->fresh();
    }
}