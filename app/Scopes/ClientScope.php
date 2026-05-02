<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class ClientScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     * Only applies when authenticated user has 'client' role.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        // Only apply scope for client role users
        if (! $user->hasRole('client')) {
            return;
        }

        $clientId = $user->client?->id;

        if (! $clientId) {
            $builder->whereRaw('1 = 0');

            return;
        }

        // Apply filtering based on model type
        if ($model instanceof \App\Modules\Project\Models\Project) {
            $builder->where('client_id', $clientId);
        }
    }
}
