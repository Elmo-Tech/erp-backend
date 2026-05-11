<?php

namespace App\Modules\HR\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\HR\Models\LeaveRequest
 */
class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'userId'        => $this->user_id,
            'startDate'     => $this->start_date?->toDateString(),
            'endDate'       => $this->end_date?->toDateString(),
            'durationDays'  => $this->durationInDays(),
            'type'          => $this->type,
            'status'        => $this->status,
            'reason'        => $this->reason,
            'user'          => $this->whenLoaded('user', fn () => [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'email' => $this->user->email,
            ]),
            'createdAt'     => $this->created_at?->toIso8601String(),
            'updatedAt'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
