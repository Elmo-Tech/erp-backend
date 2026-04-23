<?php

namespace App\Modules\Project\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'memberId' => $this->id,
            'projectId' => $this->project_id,
            'userId' => $this->user_id,
            'role' => $this->role ?? 'member',
            'user' => $this->whenLoaded('user', function () {
                return [
                    'userId' => $this->user->id,
                    'name' => $this->user->name ?? '',
                    'email' => $this->user->email ?? '',
                    'avatar' => $this->user->avatar,
                ];
            }),
            'createdAt' => $this->created_at?->toISOString() ?? '',
        ];
    }
}
