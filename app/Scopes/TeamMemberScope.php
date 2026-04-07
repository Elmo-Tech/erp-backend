<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TeamMemberScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     * Filters projects to only show those where the user is a team member.
     * Does not apply to admins or users with view_all_projects permission.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        // Don't apply scope for clients (they have their own ClientScope)
        if ($user->hasRole('client')) {
            return;
        }

        // Don't apply scope for admins
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return;
        }

        // Don't apply scope for users with view_all_projects permission
        if ($user->can('view_all_projects')) {
            return;
        }

        // Apply filtering - only show projects where user is a member
        if ($model instanceof \App\Models\Project) {
            $builder->whereHas('members', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            });
        }
    }
}
