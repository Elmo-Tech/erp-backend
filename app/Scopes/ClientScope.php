<?php

namespace App\Scopes;

use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class ClientScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     * Only applies when the authenticated identity owns a client profile.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        if (! $user->client()->exists()) {
            return;
        }

        $clientId = $user->client?->id;

        if (! $clientId) {
            $builder->whereRaw('1 = 0');

            return;
        }

        // Apply filtering based on model type
        if ($model instanceof Project) {
            $builder->where('client_id', $clientId);
        } elseif ($model instanceof Invoice) {
            $builder->whereHas('project', function ($query) use ($clientId) {
                $query->where('client_id', $clientId);
            });
        }
    }
}
