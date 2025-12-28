<?php

namespace App\Observers;

use App\Enums\ProjectStatus;
use App\Jobs\AutoApproveUIJob;
use App\Models\Project;
use App\Services\Projects\ProjectHistoryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;

/**
 * Observer for Project model to handle status changes and auto-approval scheduling.
 *
 * This observer:
 * - Tracks status changes and stores previous_status for on_hold recovery
 * - Schedules AutoApproveUIJob when entering ui_ux status (21 days delay)
 * - Cancels scheduled job when leaving ui_ux status
 * - Records status changes in project history
 *
 * Requirements: 5.1, 5.5, 5.6
 */
class ProjectObserver
{
    /**
     * Create a new observer instance.
     */
    public function __construct(
        private ProjectHistoryService $historyService
    ) {}

    /**
     * Handle the Project "updating" event.
     *
     * This method is called before the model is saved to the database.
     * It handles:
     * - Storing previous_status when transitioning to on_hold
     * - Scheduling AutoApproveUIJob when entering ui_ux status
     * - Canceling scheduled job when leaving ui_ux status
     *
     * @param Project $project
     * @return void
     */
    public function updating(Project $project): void
    {
        // Only process if status is being changed
        if (!$project->isDirty('status')) {
            return;
        }

        $oldStatus = $project->getOriginal('status');
        $newStatus = $project->status;

        // Handle case where old status might be a string (from database)
        if (is_string($oldStatus)) {
            $oldStatus = ProjectStatus::tryFrom($oldStatus);
        }

        // Store previous status when transitioning to on_hold
        // This allows returning to the previous status when resuming
        if ($newStatus === ProjectStatus::ON_HOLD && $oldStatus !== null) {
            $project->previous_status = $oldStatus;
        }

        // Schedule auto-approval job when entering UI/UX phase
        if ($newStatus === ProjectStatus::UI_UX && $oldStatus !== ProjectStatus::UI_UX) {
            $this->scheduleAutoApprovalJob($project);
        }

        // Cancel auto-approval job when leaving UI/UX phase
        // (either manually approved, status changed, or cancelled)
        if ($oldStatus === ProjectStatus::UI_UX && $newStatus !== ProjectStatus::UI_UX) {
            $this->cancelAutoApprovalJob($project);
        }
    }

    /**
     * Handle the Project "updated" event.
     *
     * This method is called after the model has been saved to the database.
     * It records status changes in the project history for audit trail.
     *
     * @param Project $project
     * @return void
     */
    public function updated(Project $project): void
    {
        // Record status changes in history
        if ($project->wasChanged('status')) {
            $oldStatus = $project->getOriginal('status');
            $newStatus = $project->status;

            // Handle case where old status might be a string
            if (is_string($oldStatus)) {
                $oldStatusValue = $oldStatus;
            } elseif ($oldStatus instanceof ProjectStatus) {
                $oldStatusValue = $oldStatus->value;
            } else {
                $oldStatusValue = null;
            }

            $newStatusValue = $newStatus instanceof ProjectStatus
                ? $newStatus->value
                : (string) $newStatus;

            $this->historyService->recordChange(
                $project,
                'status',
                $oldStatusValue,
                $newStatusValue
            );
        }
    }

    /**
     * Schedule the AutoApproveUIJob for 21 days from now.
     *
     * The job will automatically approve UI/UX deliverables if the client
     * hasn't responded within 21 days.
     *
     * @param Project $project
     * @return void
     */
    private function scheduleAutoApprovalJob(Project $project): void
    {
        // Dispatch the job with a 21-day delay
        $job = new AutoApproveUIJob($project->id);
        $dispatchedJob = Bus::dispatch($job->delay(Carbon::now()->addDays(21)));

        // Store the job ID for potential cancellation
        // Note: The actual job ID storage depends on the queue driver
        // For database queue, we can use the job's chain ID or a custom identifier
        // For simplicity, we store a timestamp-based identifier
        $project->auto_approval_job_id = 'auto_approve_' . $project->id . '_' . time();
    }

    /**
     * Cancel the scheduled AutoApproveUIJob.
     *
     * This is called when:
     * - Client manually approves before 21 days
     * - Project status changes away from ui_ux
     * - Project is cancelled
     *
     * @param Project $project
     * @return void
     */
    private function cancelAutoApprovalJob(Project $project): void
    {
        // Clear the job ID reference
        // Note: Actual job cancellation depends on the queue driver
        // For database queue, the job will check if project is still in ui_ux status
        // and skip execution if not (defensive check in AutoApproveUIJob::handle)
        $project->auto_approval_job_id = null;
    }
}
