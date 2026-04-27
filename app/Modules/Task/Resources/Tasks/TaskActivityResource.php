<?php

namespace App\Modules\Task\Resources\Tasks;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="TaskActivityResource",
 *     type="object",
 *
 *     @OA\Property(property="activityId", type="integer"),
 *     @OA\Property(property="taskId", type="integer"),
 *     @OA\Property(property="userId", type="integer"),
 *     @OA\Property(property="type", type="string"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="comment", type="string", nullable=true),
 *     @OA\Property(property="isInternal", type="boolean"),
 *     @OA\Property(property="metadata", type="object"),
 *     @OA\Property(property="user", type="object"),
 *     @OA\Property(property="createdAt", type="string", format="date-time")
 * )
 */
class TaskActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'activityId' => $this->id,
            'taskId' => $this->task_id,
            'userId' => $this->user_id,
            'type' => $this->type,
            'description' => $this->description ?? '',
            'comment' => $this->comment,
            'isInternal' => (bool) $this->is_internal,
            'metadata' => $this->metadata ?? [],
            'user' => $this->whenLoaded('user', fn () => [
                'userId' => $this->user->id,
                'name' => $this->user->name,
                'avatar' => $this->user->avatar,
            ]),
            'createdAt' => $this->created_at?->format('Y-m-d\TH:i:s') ?? '',
        ];
    }
}
