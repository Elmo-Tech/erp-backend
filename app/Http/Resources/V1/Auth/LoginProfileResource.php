<?php

namespace App\Http\Resources\V1\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'LoginProfileResource',
    description: 'User profile information for login response',
    properties: [
        new OA\Property(property: 'userId', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
        new OA\Property(property: 'emailVerifiedAt', type: 'string', format: 'date-time', nullable: true, example: '2024-01-15T10:30:00Z'),
        new OA\Property(property: 'avatar', type: 'string', nullable: true, example: 'https://example.com/avatar.jpg'),
        new OA\Property(property: 'skills', type: 'array', items: new OA\Items(type: 'string'), example: ['PHP', 'Laravel', 'JavaScript']),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00Z'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2024-01-15T10:30:00Z'),
    ]
)]
class LoginProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'userId' => $this->id,
            'name' => $this->name ?? "",
            'email' => $this->email ?? "",
            'emailVerifiedAt' => $this->email_verified_at?->toISOString() ?? null,
            'avatar' => $this->avatar ?? "",
            'skills' => $this->skills ?? [],
            'createdAt' => $this->created_at?->toISOString() ?? "",
            'updatedAt' => $this->updated_at?->toISOString() ?? "",
        ];
    }
}