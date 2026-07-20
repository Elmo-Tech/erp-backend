<?php

namespace App\Modules\Project\Services;

use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;

class ProjectTaskRelationshipValidator
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function validate(User $actor, Project $project, array $data): void
    {
        if (array_key_exists('milestoneId', $data) && $data['milestoneId'] !== null) {
            abort_unless(
                $project->milestones()->whereKey((int) $data['milestoneId'])->exists(),
                422,
                'The selected milestone does not belong to this project.'
            );
        }

        if (! array_key_exists('assigneeId', $data) || $data['assigneeId'] === null) {
            return;
        }

        abort_unless($actor->can('assign_tasks'), 403, 'Unauthorized');
        abort_unless(
            $project->members()->where('user_id', (int) $data['assigneeId'])->exists(),
            422,
            'The selected assignee is not a project member.'
        );
    }
}
