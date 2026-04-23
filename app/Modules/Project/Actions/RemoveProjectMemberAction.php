<?php

namespace App\Modules\Project\Actions;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMember;
use RuntimeException;

class RemoveProjectMemberAction
{
    public function execute(Project $project, ProjectMember $member): void
    {
        if ($member->project_id !== $project->id) {
            throw new RuntimeException('Member not found');
        }

        $member->delete();
    }
}
