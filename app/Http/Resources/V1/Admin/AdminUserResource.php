<?php

namespace App\Http\Resources\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AdminUserResource',
    description: 'User information for admin management with conditional internal cost visibility',
    properties: [
        new OA\Property(property: 'userId', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
        new OA\Property(property: 'emailVerifiedAt', type: 'string', format: 'date-time', nullable: true, example: '2024-01-15T10:30:00Z'),
        new OA\Property(property: 'avatar', type: 'string', nullable: true, example: 'https://example.com/avatar.jpg'),
        new OA\Property(property: 'skills', type: 'array', items: new OA\Items(type: 'string'), example: ['PHP', 'Laravel', 'JavaScript']),
        new OA\Property(property: 'internalHourlyCost', type: 'number', format: 'float', nullable: true, example: 75.50, description: 'Only visible to users with view_internal_costs permission'),
        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), example: ['developer', 'admin']),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00Z'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2024-01-15T10:30:00Z'),
    ]
)]
class AdminUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'userId' => $this->id,
            'name' => $this->name ?? "",
            'email' => $this->email ?? "",
            'emailVerifiedAt' => $this->email_verified_at?->toISOString() ?? null,
            'avatar' => $this->avatar,
            'skills' => $this->skills ?? [],
            'roles' => $this->roles->pluck('name')->toArray(),
            'createdAt' => $this->created_at?->toISOString() ?? "",
            'updatedAt' => $this->updated_at?->toISOString() ?? "",
        ];

        // Conditionally include internal hourly cost based on permission
        if ($request->user()?->can('view_internal_costs')) {
            $data['internalHourlyCost'] = $this->internal_hourly_cost ? (float) $this->internal_hourly_cost : 0.0;
        }

        return $data;
    }
}