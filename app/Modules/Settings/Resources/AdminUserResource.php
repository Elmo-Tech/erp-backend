<?php

namespace App\Modules\Settings\Resources;

use App\Modules\Settings\Models\User;
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
/**
 * @mixin User
 */
class AdminUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        $data = [
            'userId' => $user->id,
            'name' => $user->name ?? '',
            'email' => $user->email ?? '',
            'emailVerifiedAt' => $user->email_verified_at?->toISOString() ?? null,
            'avatar' => $user->avatar,
            'skills' => $user->skills ?? [],
            'roles' => $user->roles->pluck('name')->toArray(),
            'createdAt' => $user->created_at?->toISOString() ?? '',
            'updatedAt' => $user->updated_at?->toISOString() ?? '',
        ];

        // Conditionally include internal hourly cost based on permission
        if ($request->user()?->can('view_internal_costs')) {
            $data['internalHourlyCost'] = $user->internal_hourly_cost ? (float) $user->internal_hourly_cost : 0.0;
        }

        return $data;
    }
}
