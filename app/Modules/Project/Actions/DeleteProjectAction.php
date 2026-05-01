<?php

namespace App\Modules\Project\Actions;

use App\Modules\Project\Models\Project;

class DeleteProjectAction
{
    public function execute(Project $project): void
    {
        $project->delete();
    }
}
