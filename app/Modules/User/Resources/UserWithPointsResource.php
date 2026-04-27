<?php

namespace App\Modules\User\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserWithPointsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'userId' => $this->id ?? 0,
            'name' => $this->name ?? '',
            'email' => $this->email ?? '',
            'avatar' => $this->avatar,
            'totalPoints' => $this->total_points ?? 0,
            'isActive' => $this->is_active ?? false,
            'createdAt' => $this->created_at?->toISOString() ?? '',
        ];
    }
}
