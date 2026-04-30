<?php

namespace App\Modules\Settings\Resources;

use App\Modules\Settings\Models\User;
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
        new OA\Property(property: 'graceDaysAllowed', type: 'integer', example: 2),
        new OA\Property(property: 'graceDaysUsed', type: 'integer', example: 0),
        new OA\Property(property: 'graceDaysRemaining', type: 'integer', example: 2),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
    ]
)]
/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'userId' => $user->id,
            'name' => $user->name ?? '',
            'email' => $user->email ?? '',
            'emailVerifiedAt' => $user->email_verified_at?->toISOString(),
            'skills' => $user->skills ?? [],
            'specialization' => $user->specialization->value,
            'specializationLabel' => $user->specialization->label(),
            'specializationColor' => $user->specialization->color(),
            'roles' => $user->roles->pluck('name')->toArray(),
            'isActive' => $user->is_active,
            'graceDaysAllowed' => (int) ($user->grace_days_allowed ?? 2),
            'graceDaysUsed' => (int) ($user->grace_days_used ?? 0),
            'graceDaysRemaining' => max(0, (int) ($user->grace_days_allowed ?? 2) - (int) ($user->grace_days_used ?? 0)),
            'createdAt' => $user->created_at?->toISOString() ?? '',
            'updatedAt' => $user->updated_at?->toISOString() ?? '',
        ];
    }
}
