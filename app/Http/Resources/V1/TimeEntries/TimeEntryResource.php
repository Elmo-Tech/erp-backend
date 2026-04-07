<?php

namespace App\Http\Resources\V1\TimeEntries;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get trackable display name if trackable is loaded
        $trackableDisplayName = '';
        if ($this->relationLoaded('trackable') && $this->trackable) {
            $trackableDisplayName = $this->trackable->getTrackableDisplayName();
        }

        return [
            'timeEntryId' => $this->id,
            'userId' => $this->user_id ?? 0,
            'trackableId' => $this->trackable_id ?? 0,
            'trackableType' => $this->trackable_type ?? '',
            'trackableDisplayName' => $trackableDisplayName,
            'startTime' => $this->start_time?->format('Y-m-d\TH:i:s') ?? '',
            'endTime' => $this->end_time?->format('Y-m-d\TH:i:s') ?? '',
            'durationMinutes' => $this->duration_minutes ?? 0,
            'isActive' => $this->isActive(),
            'createdAt' => $this->created_at?->format('Y-m-d\TH:i:s') ?? '',
            'updatedAt' => $this->updated_at?->format('Y-m-d\TH:i:s') ?? '',
        ];
    }
}
