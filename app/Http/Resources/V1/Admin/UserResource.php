<?php

namespace App\Http\Resources\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UserResource',
    description: 'Detailed user information for admin management',
    properties: [
        new OA\Property(property: 'userId', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
        new OA\Property(property: 'emailVerifiedAt', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'skills', type: 'array', items: new OA\Items(type: 'string'), example: ['PHP', 'Laravel']),
        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), example: ['developer']),
        new OA\Property(property: 'isActive', type: 'boolean', example: true),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
    ]
)]
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'userId' => $this->id,
            'name' => $this->name ?? "",
            'email' => $this->email ?? "",
            'emailVerifiedAt' => $this->email_verified_at?->toISOString(),
            'skills' => $this->skills ?? [],
            'roles' => $this->roles->pluck('name')->toArray(),
            'isActive' => (bool) ($this->is_active ?? true),
            'createdAt' => $this->created_at?->toISOString() ?? "",
            'updatedAt' => $this->updated_at?->toISOString() ?? "",
        ];
    }
}
