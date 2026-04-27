<?php

namespace App\Modules\Task\Resources\Tasks;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="TaskAssigneeResource",
 *     type="object",
 *
 *     @OA\Property(property="assigneeId", type="integer", example=1),
 *     @OA\Property(property="taskId", type="integer", example=1),
 *     @OA\Property(property="userId", type="integer", example=2),
 *     @OA\Property(property="role", type="string", enum={"developer", "reviewer", "lead"}),
 *     @OA\Property(property="isPrimary", type="boolean", example=true),
 *     @OA\Property(property="pointsEarned", type="integer", example=5),
 *     @OA\Property(property="user", type="object")
 * )
 */
class TaskAssigneeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'assigneeId' => $this->id,
            'taskId' => $this->task_id,
            'userId' => $this->user_id,
            'role' => $this->role,
            'isPrimary' => (bool) $this->is_primary,
            'pointsEarned' => (int) $this->points_earned,
            'user' => $this->whenLoaded('user', fn () => [
                'userId' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'avatar' => $this->user->avatar,
            ]),
            'createdAt' => $this->created_at?->toISOString() ?? '',
        ];
    }
}
