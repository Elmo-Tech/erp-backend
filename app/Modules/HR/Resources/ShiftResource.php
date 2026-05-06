<?php

namespace App\Modules\HR\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\HR\Models\Shift
 */
class ShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'startTime'            => $this->start_time,
            'endTime'              => $this->end_time,
            'workingDays'          => $this->working_days,
            'gracePeriodMinutes'   => $this->grace_period_minutes,
            'employeeProfilesCount' => $this->whenCounted('employeeProfiles'),
            'createdAt'            => $this->created_at?->toIso8601String(),
            'updatedAt'            => $this->updated_at?->toIso8601String(),
        ];
    }
}
