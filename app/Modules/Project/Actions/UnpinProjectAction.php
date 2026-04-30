<?php

namespace App\Modules\Project\Actions;

use App\Modules\Settings\Models\User;
use App\Modules\Project\Models\Project;
use RuntimeException;

class UnpinProjectAction
{
    public function execute(User $user, Project $project): void
    {
        if (! $project->isPinnedBy($user->id)) {
            throw new RuntimeException('Project is not pinned');
        }

        $user->pinnedProjects()->detach($project->id);
    }
}
