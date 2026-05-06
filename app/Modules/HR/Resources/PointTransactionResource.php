<?php

namespace App\Modules\HR\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formats a single PointTransaction record for the employee-facing
 * GET /api/v1/hr/my-points endpoint.
 *
 * Fields:
 *   id           – transaction primary key
 *   points       – signed integer (positive = earned, negative = deducted)
 *   reason       – human-readable description of why points were awarded/removed
 *   model_type   – simplified source type label (e.g. 'task', 'attendance')
 *   model_id     – ID of the source record
 *   created_at   – ISO-8601 timestamp
 */
class PointTransactionResource extends JsonResource
{
    /**
     * Map raw source_type class names to short, frontend-friendly labels.
     *
     * @var array<string, string>
     */
    private const SOURCE_TYPE_MAP = [
        'App\Models\Task'           => 'task',
        'App\Models\Attendance'     => 'attendance',
        'App\Models\CodeReview'     => 'code_review',
        'App\Models\ProjectMember'  => 'project',
        // Fallback: any unmapped class will be slugified in toArray()
    ];

    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'points'     => (int) $this->points,
            'reason'     => $this->reason,
            'model_type' => $this->resolveModelType($this->source_type),
            'model_id'   => $this->source_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Convert a fully-qualified class name to a short label.
     * Falls back to the last segment of the class name, lowercased.
     */
    private function resolveModelType(?string $sourceType): ?string
    {
        if ($sourceType === null) {
            return null;
        }

        if (isset(self::SOURCE_TYPE_MAP[$sourceType])) {
            return self::SOURCE_TYPE_MAP[$sourceType];
        }

        // e.g. "App\Modules\Project\Models\Task" → "task"
        $parts = explode('\\', $sourceType);

        return strtolower(end($parts));
    }
}
