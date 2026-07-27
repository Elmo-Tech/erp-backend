<?php

namespace App\Scopes;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TeamMemberScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     * Filters projects to only show those where the user is a team member.
     * Does not apply to client portal identities or users with view_all_projects permission.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        // Client portal identities have their own ClientScope.
        if ($user->client()->exists()) {
            return;
        }

        if ($user->can('view_all_projects')) {
            return;
        }

        // Apply filtering - only show projects where user is a member
        if ($model instanceof Project) {
            $builder->whereHas('members', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            });
        }
    }
}
