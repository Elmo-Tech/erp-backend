<?php

namespace App\Modules\Project\Actions;

use App\Modules\Settings\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ListPinnedProjectsAction
{
    public function execute(User $user): Collection
    {
        return $user->pinnedProjects()
            ->with(['client'])
            ->orderByPivot('created_at', 'desc')
            ->get();
    }
}
