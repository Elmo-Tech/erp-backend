<?php

namespace App\Modules\Calendar\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendarEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'title' => $this->title,
            'description' => $this->description,
            'startTime' => $this->start_time?->toIso8601String(),
            'endTime' => $this->end_time?->toIso8601String(),
            'eventType' => $this->event_type,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'avatar' => $this->creator->avatar ?? null,
            ]),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
