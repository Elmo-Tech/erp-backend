<?php

namespace App\Modules\Project\Actions;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMember;
use RuntimeException;

class AddProjectMemberAction
{
    public function execute(Project $project, int $userId, string $role = 'member'): ProjectMember
    {
        if ($project->members()->where('user_id', $userId)->exists()) {
            throw new RuntimeException('User is already a member of this project');
        }

        return ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $userId,
            'role' => $role,
        ]);
    }
}
