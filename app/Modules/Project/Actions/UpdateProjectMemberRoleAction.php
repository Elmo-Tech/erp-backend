<?php

namespace App\Modules\Project\Actions;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMember;
use RuntimeException;

class UpdateProjectMemberRoleAction
{
    public function execute(Project $project, ProjectMember $member, string $role): ProjectMember
    {
        if ($member->project_id !== $project->id) {
            throw new RuntimeException('Member not found');
        }

        $member->update(['role' => $role]);

        return $member->fresh();
    }
}
