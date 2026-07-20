<?php

namespace App\Scopes;

use App\Modules\Client\Models\Client;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Project\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class ClientScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * Scopes the query to the authenticated client's own data.
     * Uses the access_client_portal permission instead of a hardcoded role
     * check, so any user granted that permission is treated as a portal user.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        // Only apply scope for client-portal users.
        if (! $user->can('access_client_portal')) {
            return;
        }

        $clientId = $user->client?->id;

        if (! $clientId) {
            // Portal user with no linked client record — deny all rows.
            $builder->whereRaw('1 = 0');

            return;
        }

        // Apply filtering based on model type.
        if ($model instanceof Project) {
            $builder->where('client_id', $clientId);
        }

        if ($model instanceof Invoice) {
            $builder->where(function (Builder $query) use ($clientId) {
                $query->where(function (Builder $directClientQuery) use ($clientId) {
                    $directClientQuery->where('model_type', Client::class)
                        ->where('model_id', $clientId);
                })->orWhere(function (Builder $projectQuery) use ($clientId) {
                    $projectQuery->where('model_type', Project::class)
                        ->whereIn('model_id', Project::withoutGlobalScopes()
                            ->select('id')
                            ->where('client_id', $clientId));
                });
            });
        }
    }
}
