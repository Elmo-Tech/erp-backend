<?php

namespace App\Http\Resources\V1\UserPoints;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaderboardResource extends JsonResource
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
            'avatar' => $this->avatar,
            'totalPoints' => $this->total_points ?? 0,
            'rank' => $this->rank ?? 0, // This will be set in the collection
        ];
    }
}