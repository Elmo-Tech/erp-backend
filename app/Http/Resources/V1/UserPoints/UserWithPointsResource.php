<?php

namespace App\Http\Resources\V1\UserPoints;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'name' => $this->name ?? "",
            'email' => $this->email ?? "",
            'avatar' => $this->avatar ? Storage::disk('public')->url($this->avatar) : "",
            'totalPoints' => $this->total_points ?? 0,
            'isActive' => $this->is_active ?? false,
            'createdAt' => $this->created_at?->toISOString() ?? "",
        ];
    }
}