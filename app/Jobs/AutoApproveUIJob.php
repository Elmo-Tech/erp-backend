<?php

namespace App\Jobs;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Services\Projects\ProjectHistoryService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job to auto-approve UI/UX deliverables after 21 days without client response.
 *
 * This job is scheduled when a project enters the UI/UX phase and will
 * automatically approve the UI deliverables if the client hasn't responded
 * within 21 days. This prevents projects from stalling indefinitely.
 *
 * Requirements: 5.2, 5.3, 5.4
 */
class AutoApproveUIJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param int $projectId The ID of the project to auto-approve
     */
    public function __construct(
        public int $projectId
    ) {}

    /**
     * Execute the job.
     *
     * This method:
     * 1. Finds the project by ID
     * 2. Verifies the project is still in UI/UX status
     * 3. Checks if it hasn't been manually approved
     * 4. Sets ui_approved_at to current timestamp
     * 5. Changes status from 'ui_ux' to 'development'
     * 6. Records the auto-approval in project history
     * 7. Clears the auto_approval_job_id
     *
     * @param ProjectHistoryService $historyService
     * @return void
     */
    public function handle(ProjectHistoryService $historyService): void
    {
        $project = Project::find($this->projectId);

        // Skip if project not found
        if (!$project) {
            return;
        }

        // Skip if project is no longer in UI/UX phase
        // (status may have changed since job was scheduled)
        if ($project->status !== ProjectStatus::UI_UX) {
            return;
        }

        // Skip if already manually approved
        // (client approved before the 21-day deadline)
        if ($project->ui_approved_at !== null) {
            return;
        }

        // Store old status for history record
        $oldStatus = $project->status->value;
        $approvalTimestamp = Carbon::now();

        // Auto-approve: update project status and approval timestamp
        $project->update([
            'ui_approved_at' => $approvalTimestamp,
            'status' => ProjectStatus::DEVELOPMENT,
            'auto_approval_job_id' => null,
        ]);

        // Record status change in history with auto-approval reason
        $historyService->recordChange(
            $project,
            'status',
            $oldStatus,
            ProjectStatus::DEVELOPMENT->value,
            'Auto-approved after 21 days'
        );

        // Record ui_approved_at change in history
        $historyService->recordChange(
            $project,
            'ui_approved_at',
            null,
            $approvalTimestamp->toISOString(),
            'Auto-approved after 21 days'
        );
    }
}
