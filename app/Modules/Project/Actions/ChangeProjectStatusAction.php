<?php

namespace App\Modules\Project\Actions;

use App\Modules\Project\Enums\ProjectStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use Illuminate\Support\Facades\DB;

class ChangeProjectStatusAction
{
    public function __construct(
        private UpdateProjectStatusAction $updateProjectStatusAction
    ) {}

    /**
     * @param  array{status: string, reason?: string|null}  $data
     * @return array{project: Project, already_current: bool, status: ProjectStatus}
     */
    public function execute(int $projectId, array $data, User $user): array
    {
        $project = Project::withoutGlobalScopes()
            ->with(['client'])
            ->find($projectId);

        abort_if(! $project, 404, 'Project not found');
        abort_unless($user->can('update', $project), 403, 'You do not have permission to update this project');

        return DB::transaction(function () use ($project, $data) {
            $newStatus = ProjectStatus::from($data['status']);

            if ($project->status === $newStatus) {
                return [
                    'project' => $project,
                    'already_current' => true,
                    'status' => $newStatus,
                ];
            }

            return [
                'project' => $this->updateProjectStatusAction->execute(
                    $project,
                    $newStatus,
                    $data['reason'] ?? null
                ),
                'already_current' => false,
                'status' => $newStatus,
            ];
        });
    }
}
