<?php

namespace App\Modules\Project\Actions;

use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListProjectsAction
{
    public function execute(User $user, int $perPage = 15): LengthAwarePaginator
    {
        $query = QueryBuilder::for(Project::class)
            ->with(['client'])
            ->leftJoin('pinned_projects', function ($join) use ($user) {
                $join->on('projects.id', '=', 'pinned_projects.project_id')
                    ->where('pinned_projects.user_id', '=', $user->id);
            })
            ->select('projects.*', 'pinned_projects.id as is_pinned')
            ->allowedFilters([
                'status',
                AllowedFilter::exact('client_id', 'client_id')->ignore(null),
                AllowedFilter::callback('clientId', function ($query, $value) {
                    $query->where('client_id', $value);
                }),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where('title', 'like', "%{$value}%");
                }),
            ]);

        // ── SECURITY: Project-level scoping ───────────────────────────────────
        // manage_all_projects → unrestricted visibility (global admin/PMO bypass).
        // Otherwise, users only see projects they are explicitly assigned to
        // as team members.
        if (! $user->can('manage_all_projects')) {
            $userId = $user->id;
            $query->whereHas('members', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });
        }

        return $query
            ->orderByRaw('CASE WHEN pinned_projects.id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('projects.created_at', 'desc')
            ->paginate($perPage);
    }
}
