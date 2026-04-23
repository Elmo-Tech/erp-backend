<?php

namespace App\Modules\Project\Actions;

use App\Modules\Project\Enums\ProjectStatus;
use App\Modules\Project\Models\Project;

class UpdateProjectStatusAction
{
    public function __construct(
        private ProjectHistoryAction $historyAction
    ) {}

    public function execute(Project $project, ProjectStatus $newStatus, ?string $reason = null): Project
    {
        $oldStatus = $project->status;

        if ($oldStatus === $newStatus) {
            return $project;
        }

        $project->update([
            'status' => $newStatus,
            'previous_status' => $oldStatus,
        ]);

        $this->historyAction->recordChange(
            $project,
            'status',
            $oldStatus?->value,
            $newStatus->value,
            $reason ?? 'Status updated via API'
        );

        return $project->fresh();
    }
}
