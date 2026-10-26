<?php

namespace App\Policies;

use App\Models\User;

class WorkMonitoringPolicy
{
    public function view(User $user): bool
    {
        return $user->is_active && (
            $user->can('view_all_time_entries')
            || ($user->hasAnyRole(['admin', 'super-admin'])
                && ($user->can('view_dashboard') || $user->can('view_dashboard_overview')))
        );
    }
}
