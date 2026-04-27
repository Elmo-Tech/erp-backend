<?php

namespace App\Modules\Project\Actions;

use App\Modules\User\Models\User;
use App\Modules\Project\Models\Project;
use RuntimeException;

class PinProjectAction
{
    public function execute(User $user, Project $project): Project
    {
        if ($project->isPinnedBy($user->id)) {
            throw new RuntimeException('Project is already pinned');
        }

        $user->pinnedProjects()->attach($project->id);

        return $project->refresh();
    }
}
