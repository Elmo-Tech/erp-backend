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
        new OA\Property(property: 'graceDaysAllowed', type: 'integer', example: 2),
        new OA\Property(property: 'graceDaysUsed', type: 'integer', example: 0),
        new OA\Property(property: 'graceDaysRemaining', type: 'integer', example: 2),
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
            'specialization' => $this->specialization?->value ?? 'fullstack',
            'specializationLabel' => $this->specialization?->label() ?? 'Full Stack Developer',
            'specializationColor' => $this->specialization?->color() ?? 'bg-purple-100 text-purple-800',
            'roles' => $this->roles->pluck('name')->toArray(),
            'isActive' => (bool) ($this->is_active ?? true),
            'graceDaysAllowed' => (int) ($this->grace_days_allowed ?? 2),
            'graceDaysUsed' => (int) ($this->grace_days_used ?? 0),
            'graceDaysRemaining' => max(0, ($this->grace_days_allowed ?? 2) - ($this->grace_days_used ?? 0)),
            'createdAt' => $this->created_at?->toISOString() ?? "",
            'updatedAt' => $this->updated_at?->toISOString() ?? "",
        ];
    }
}
