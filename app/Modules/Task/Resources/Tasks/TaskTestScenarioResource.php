<?php

namespace App\Modules\Task\Resources\Tasks;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="TaskTestScenarioResource",
 *     type="object",
 *
 *     @OA\Property(property="scenarioId", type="integer", example=1),
 *     @OA\Property(property="taskId", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="User can login with valid credentials"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="expectedResult", type="string", nullable=true),
 *     @OA\Property(property="status", type="string", enum={"pending", "passed", "failed", "skipped"}),
 *     @OA\Property(property="order", type="integer", example=1),
 *     @OA\Property(property="completedAt", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="verifiedAt", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="reviewerNotes", type="string", nullable=true)
 * )
 */
class TaskTestScenarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'scenarioId' => $this->id,
            'taskId' => $this->task_id,
            'title' => $this->title,
            'description' => $this->description ?? '',
            'expectedResult' => $this->expected_result ?? '',
            'status' => $this->status,
            'order' => (int) $this->order,
            'completedBy' => $this->whenLoaded('completedBy', fn () => $this->completedBy ? [
                'userId' => $this->completedBy->id,
                'name' => $this->completedBy->name,
            ] : null),
            'completedAt' => $this->completed_at?->toISOString(),
            'verifiedBy' => $this->whenLoaded('verifiedBy', fn () => $this->verifiedBy ? [
                'userId' => $this->verifiedBy->id,
                'name' => $this->verifiedBy->name,
            ] : null),
            'verifiedAt' => $this->verified_at?->toISOString(),
            'reviewerNotes' => $this->reviewer_notes ?? '',
            'createdAt' => $this->created_at?->toISOString() ?? '',
        ];
    }
}
