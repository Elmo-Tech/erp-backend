<?php

namespace App\Http\Resources\V1\TimeEntries;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Services\Tasks\ActiveWorkService;
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
        $timeEntry = $this->resource instanceof TimeEntry ? $this->resource : null;
        $trackable = $timeEntry?->relationLoaded('trackable')
            ? $timeEntry->getRelation('trackable')
            : null;
        $target = $timeEntry ? app(ActiveWorkService::class)->target($timeEntry) : null;
        if ($target && $target['task'] instanceof Task) {
            $trackable = $target['task'];
            $trackableDisplayName = $trackable->getTrackableDisplayName();
        }
        if ($trackableDisplayName === '' && $target && $target['status'] !== 'available') {
            $trackableDisplayName = 'Deleted task #'.$this->trackable_id;
        }

        return [
            'timeEntryId' => $this->id,
            'userId' => $this->user_id ?? 0,
            'sessionId' => $this->session_id ?? '',
            'trackableId' => $this->trackable_id ?? 0,
            'trackableType' => $this->trackable_type ?? '',
            'trackableDisplayName' => $trackableDisplayName,
            'trackableProjectId' => $trackable instanceof Task
                ? $trackable->project_id
                : null,
            'startTime' => $this->start_time?->format('Y-m-d\TH:i:s') ?? '',
            'endTime' => $this->end_time?->format('Y-m-d\TH:i:s') ?? '',
            'durationMinutes' => $this->duration_minutes ?? 0,
            'lastHeartbeatAt' => $timeEntry?->heartbeatAt()?->format('Y-m-d\TH:i:s') ?? '',
            'stopReason' => $timeEntry?->stopReason()?->value,
            'resumedFromId' => $timeEntry?->getAttribute('resumed_from_id'),
            'isActive' => $this->isActive(),
            'targetStatus' => $target['status'] ?? 'available',
            'targetMessage' => $target['message'] ?? null,
            'createdAt' => $this->created_at?->format('Y-m-d\TH:i:s') ?? '',
            'updatedAt' => $this->updated_at?->format('Y-m-d\TH:i:s') ?? '',
        ];
    }
}
