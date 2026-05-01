<?php

namespace App\Modules\Meetings\Actions;

use App\Modules\Meetings\Models\Meeting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListMeetingsAction
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function execute(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Meeting::with(['subject', 'assignedTo'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['assigned_to'] ?? null, fn ($query, $userId) => $query->where('assigned_to', $userId))
            ->orderBy('scheduled_at', 'desc')
            ->paginate($perPage);
    }
}
