<?php

namespace App\Modules\Project\Actions;

use App\Modules\Project\Enums\ProjectStatus;
use App\Modules\Project\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApproveProjectUiAction
{
    public function __construct(
        private ProjectHistoryAction $historyAction
    ) {}

    public function execute(Project $project): Project
    {
        return DB::transaction(function () use ($project) {
            if ($project->status !== ProjectStatus::UI_UX) {
                throw new InvalidArgumentException('Project is not in UI/UX phase');
            }

            if ($project->ui_approved_at !== null) {
                throw new InvalidArgumentException('UI/UX has already been approved');
            }

            $oldStatus = $project->status->value;

            $project->update([
                'ui_approved_at' => Carbon::now(),
                'status' => ProjectStatus::DEVELOPMENT,
                'auto_approval_job_id' => null,
            ]);

            $this->historyAction->recordChange(
                $project,
                'status',
                $oldStatus,
                ProjectStatus::DEVELOPMENT->value,
                'Manual UI/UX approval by client'
            );

            $this->historyAction->recordChange(
                $project,
                'ui_approved_at',
                null,
                $project->ui_approved_at->toISOString(),
                'Manual UI/UX approval by client'
            );

            return $project->fresh();
        });
    }
}
