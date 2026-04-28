<?php

namespace App\Observers;

use App\Modules\Project\Enums\ProjectStatus;
use App\Jobs\AutoApproveUIJob;
use App\Modules\Project\Models\Project;
use App\Modules\Notification\Notifications\ProjectDeadlineApproachingNotification;
use App\Modules\Notification\Notifications\ProjectStatusChangedNotification;
use App\Modules\Audit\Services\AuditService;
use App\Modules\Invoice\Services\BillingService;
use App\Modules\Project\Actions\ProjectHistoryAction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Observer for Project model to handle status changes and auto-approval scheduling.
 *
 * This observer:
 * - Tracks status changes and stores previous_status for on_hold recovery
 * - Schedules AutoApproveUIJob when entering ui_ux status (21 days delay)
 * - Cancels scheduled job when leaving ui_ux status
 * - Records status changes in project history
 * - Sends notifications to team members on status changes
 * - Clears project caches when relevant data changes
 *
 * Requirements: 5.1, 5.5, 5.6
 */
class ProjectObserver
{
    /**
     * Create a new observer instance.
     */
    public function __construct(
        private ProjectHistoryAction $historyService,
        private BillingService $billingService,
        private AuditService $auditService
    ) {}

    /**
     * Handle the Project "updating" event.
     *
     * This method is called before the model is saved to the database.
     * It handles:
     * - Storing previous_status when transitioning to on_hold
     * - Scheduling AutoApproveUIJob when entering ui_ux status
     * - Canceling scheduled job when leaving ui_ux status
     */
    public function updating(Project $project): void
    {
        // Only process if status is being changed
        if (! $project->isDirty('status')) {
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
     */
    public function updated(Project $project): void
    {
        // Clear caches when project is updated
        $project->clearAllCaches();

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

            // Notify team members about status change
            $this->notifyTeamAboutStatusChange($project, $oldStatusValue, $newStatusValue);

            // Auto-generate invoices based on status change (Expansion Pack 4)
            $this->handleAutomaticBilling($project, $oldStatusValue, $newStatusValue);
        }

        // Record deadline changes
        if ($project->wasChanged('deadline')) {
            $this->historyService->recordChange(
                $project,
                'deadline',
                $project->getOriginal('deadline')?->format('Y-m-d'),
                $project->deadline?->format('Y-m-d')
            );

            // Check if deadline is approaching (within 7 days)
            $this->checkDeadlineApproaching($project);
        }

        // Record budget changes
        if ($project->wasChanged('budget')) {
            $this->historyService->recordChange(
                $project,
                'budget',
                (string) $project->getOriginal('budget'),
                (string) $project->budget
            );
        }
    }

    /**
     * Notify team members about project status change.
     */
    private function notifyTeamAboutStatusChange(Project $project, ?string $oldStatus, string $newStatus): void
    {
        // Get all team members for this project
        $teamMembers = $project->members()
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        if ($teamMembers->isEmpty()) {
            return;
        }

        // Check if notification class exists before sending
        if (class_exists(ProjectStatusChangedNotification::class)) {
            Notification::send(
                $teamMembers,
                new ProjectStatusChangedNotification($project, $oldStatus, $newStatus)
            );
        }
    }

    /**
     * Check if project deadline is approaching and notify team.
     */
    private function checkDeadlineApproaching(Project $project): void
    {
        if (! $project->deadline) {
            return;
        }

        $daysUntilDeadline = Carbon::now()->diffInDays($project->deadline, false);

        // Notify if deadline is within 7 days and in the future
        if ($daysUntilDeadline > 0 && $daysUntilDeadline <= 7) {
            $teamMembers = $project->members()
                ->with('user')
                ->get()
                ->pluck('user')
                ->filter();

            if ($teamMembers->isNotEmpty() && class_exists(ProjectDeadlineApproachingNotification::class)) {
                Notification::send(
                    $teamMembers,
                    new ProjectDeadlineApproachingNotification($project, $daysUntilDeadline)
                );
            }
        }
    }

    /**
     * Schedule the AutoApproveUIJob for 21 days from now.
     *
     * The job will automatically approve UI/UX deliverables if the client
     * hasn't responded within 21 days.
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
        $project->auto_approval_job_id = 'auto_approve_'.$project->id.'_'.time();
    }

    /**
     * Cancel the scheduled AutoApproveUIJob.
     *
     * This is called when:
     * - Client manually approves before 21 days
     * - Project status changes away from ui_ux
     * - Project is cancelled
     */
    private function cancelAutoApprovalJob(Project $project): void
    {
        // Clear the job ID reference
        // Note: Actual job cancellation depends on the queue driver
        // For database queue, the job will check if project is still in ui_ux status
        // and skip execution if not (defensive check in AutoApproveUIJob::handle)
        $project->auto_approval_job_id = null;
    }

    /**
     * Handle automatic billing when project status changes.
     *
     * This method implements the Event-Based Billing system from Expansion Pack 4.
     * It automatically generates invoices for completed milestones based on project status.
     */
    private function handleAutomaticBilling(Project $project, ?string $oldStatus, string $newStatus): void
    {
        // Only process if project has a contract
        if (! $project->contract) {
            return;
        }

        // Log the status change for audit trail
        $this->auditService->logProjectEvent(
            action: 'status_changed',
            project: $project,
            metadata: [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'contract_id' => $project->contract->id,
            ],
            severity: 'info'
        );

        // Generate invoices based on status transitions
        try {
            $generatedInvoices = $this->billingService->generatePendingInvoices($project);

            if (! empty($generatedInvoices)) {
                Log::info('Auto-generated {count} invoices for project {project}', [
                    'count' => count($generatedInvoices),
                    'project' => $project->id,
                    'status_change' => "{$oldStatus} -> {$newStatus}",
                    'invoice_ids' => collect($generatedInvoices)->pluck('id')->toArray(),
                ]);

                // Log the automatic invoice generation
                $this->auditService->logSystemEvent(
                    action: 'auto_invoice_generation',
                    metadata: [
                        'project_id' => $project->id,
                        'status_change' => "{$oldStatus} -> {$newStatus}",
                        'invoices_generated' => count($generatedInvoices),
                        'invoice_ids' => collect($generatedInvoices)->pluck('id')->toArray(),
                    ],
                    severity: 'info'
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to auto-generate invoices for project {project}: {error}', [
                'project' => $project->id,
                'error' => $e->getMessage(),
                'status_change' => "{$oldStatus} -> {$newStatus}",
            ]);

            // Log the failure
            $this->auditService->logSystemEvent(
                action: 'auto_invoice_generation_failed',
                metadata: [
                    'project_id' => $project->id,
                    'status_change' => "{$oldStatus} -> {$newStatus}",
                    'error' => $e->getMessage(),
                ],
                severity: 'warning'
            );
        }
    }
}
