<?php

namespace App\Modules\TimeEntry\Actions;

use App\Modules\TimeEntry\Models\TimeEntry;
use Carbon\Carbon;

class LogManualTimeAction
{
    public function update(TimeEntry $timeEntry, array $data): TimeEntry
    {
        $updateData = [];

        if (array_key_exists('startTime', $data)) {
            $updateData['start_time'] = $data['startTime'];
        }

        if (array_key_exists('endTime', $data)) {
            $updateData['end_time'] = $data['endTime'];
        }

        if (array_key_exists('durationMinutes', $data)) {
            $updateData['duration_minutes'] = $data['durationMinutes'];
        }

        if (isset($updateData['start_time'], $updateData['end_time']) && ! isset($updateData['duration_minutes'])) {
            $start = Carbon::parse($updateData['start_time']);
            $end = Carbon::parse($updateData['end_time']);
            $updateData['duration_minutes'] = (int) floor($start->diffInSeconds($end) / 60);
        }

        $timeEntry->update($updateData);

        return $timeEntry->fresh();
    }
}
