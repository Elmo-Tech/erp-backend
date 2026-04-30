<?php

namespace App\Modules\Client\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'activityId' => $this->id,
            'clientId' => $this->client_id,
            'userId' => $this->user_id,
            'action' => $this->action,
            'description' => $this->description,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'userId' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null),
            'createdAt' => $this->created_at?->toISOString() ?? '',
        ];
    }
}
