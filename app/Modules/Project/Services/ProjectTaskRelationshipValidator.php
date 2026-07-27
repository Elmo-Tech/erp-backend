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
        $this->validateProjectRelationships($project, $data);

        if (! isset($data['assigneeId'])) {
            return;
        }

        abort_unless($actor->can('assign_tasks'), 403, 'Unauthorized');
        abort_unless(
            $project->members()->where('user_id', (int) $data['assigneeId'])->exists(),
            422,
            'The selected assignee is not a project member.'
        );
    }

    /** @param  array<string, mixed>  $data */
    public function validateDirectTask(
        User $actor,
        Project $project,
        array $data,
    ): void {
        $this->validateProjectRelationships($project, $data);
        $this->validateMultiAssigneeCapability($actor, $data);
        $this->validateLegacyAssignee($actor, $project, $data);
    }

    /** @param  array<string, mixed>  $data */
    public function validateDirectTaskCreation(User $actor, Project $project, array $data): void
    {
        $this->validateProjectRelationships($project, $data);

        if (! $this->isSelfAssignment($actor, $data)) {
            $this->validateMultiAssigneeCapability($actor, $data);
        }

        $this->validateLegacyAssignee($actor, $project, $data);
    }

    /** @param  array<string, mixed>  $data */
    private function validateLegacyAssignee(User $actor, Project $project, array $data): void
    {
        if (! isset($data['assigneeId'])) {
            return;
        }

        abort_unless($actor->can('assign_tasks'), 403, 'Unauthorized');
        abort_unless(
            $project->members()->where('user_id', (int) $data['assigneeId'])->exists(),
            422,
            'The selected assignee is not a project member.'
        );
    }

    /** @param  array<string, mixed>  $data */
    private function validateMultiAssigneeCapability(User $actor, array $data): void
    {
        if (! empty($data['assigneeIds'])) {
            abort_unless($actor->can('assign_tasks'), 403, 'Unauthorized');
        }
    }

    /** @param  array<string, mixed>  $data */
    private function isSelfAssignment(User $actor, array $data): bool
    {
        $assigneeIds = collect($data['assigneeIds'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->unique();

        return $assigneeIds->count() === 1
            && $assigneeIds->first() === $actor->id;
    }

    /** @param  array<string, mixed>  $data */
    private function validateProjectRelationships(Project $project, array $data): void
    {
        if (array_key_exists('milestoneId', $data) && $data['milestoneId'] !== null) {
            abort_unless(
                $project->milestones()->whereKey((int) $data['milestoneId'])->exists(),
                422,
                'The selected milestone does not belong to this project.'
            );
        }

        if (array_key_exists('workflowStageId', $data) && $data['workflowStageId'] !== null) {
            abort_unless(
                $project->workflowStages()->whereKey((int) $data['workflowStageId'])->exists(),
                422,
                'The selected workflow stage does not belong to this project.'
            );
        }
    }
}
