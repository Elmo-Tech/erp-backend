<?php

namespace App\Modules\Meetings\Actions;

use App\Modules\Meetings\Models\Meeting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListMeetingsAction
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function execute(array $filters = [], int $perPage = 15, ?string $subjectType = null, ?int $subjectId = null): LengthAwarePaginator
    {
        return Meeting::with(['subject', 'assignedTo'])
            ->when($subjectType, fn ($query) => $query->where('subject_type', $subjectType))
            ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['assigned_to'] ?? null, fn ($query, $userId) => $query->where('assigned_to', $userId))
            ->orderBy('scheduled_at', 'desc')
            ->paginate($perPage);
    }
}
