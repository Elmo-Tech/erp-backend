<?php

namespace App\Policies;

use App\Modules\Project\Enums\ExecutionTaskStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Settings\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * TaskPolicy — Strict ABAC/PBAC for project execution tasks.
 *
 * Task actions compose an exact capability with project membership. Only
 * manage_all_projects crosses project boundaries.
 *
 * Permission matrix:
 *   manage_all_projects  → unrestricted access to every action.
 *   manage_tasks         → umbrella for task actions inside assigned projects.
 *   create/edit/delete   → exact metadata actions inside assigned projects.
 *   change/complete/reopen → exact lifecycle actions inside assigned projects.
 *   work_on_tasks        → assignee execution through in_review only.
 *   manage_deliverables  → umbrella for deliverable actions inside assigned projects.
 */
class TaskPolicy
{
    use HandlesAuthorization;

    /** @var array<string, bool> */
    private array $projectMembershipCache = [];

    /** @var array<int, Project> */
    private array $projectCache = [];

    // ─────────────────────────────────────────────────────────────────────────
    // viewAny — can the user see the task list for a project?
    // ─────────────────────────────────────────────────────────────────────────
    public function viewAny(User $user, Project $project): bool
    {
        return $user->can('view_tasks');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // view — can the user open a specific task?
    // ─────────────────────────────────────────────────────────────────────────
    public function view(User $user, ProjectTask $task): bool
    {
        return $user->can('view_tasks');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // create — can the user add tasks/milestones to a project?
    //
    // Rules: manage_all_projects bypasses scope; manage_tasks/create_tasks
    // require project membership.
    // ─────────────────────────────────────────────────────────────────────────
    public function create(User $user, Project $project): bool
    {
        return $this->hasScopedPermission($user, $project, 'create_tasks');
    }

    public function createDeliverable(User $user, Project|ProjectTask $subject): bool
    {
        return $this->hasScopedDeliverablePermission(
            $user,
            $subject,
            'create_project_task_deliverables'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // update — task metadata only. Status transitions use dedicated abilities.
    // ─────────────────────────────────────────────────────────────────────────
    public function update(User $user, ProjectTask $task): bool
    {
        return $this->hasScopedPermission($user, $task, 'edit_tasks');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // delete — can the user delete a task?
    //
    // Rules: manage_all_projects bypasses scope; manage_tasks/delete_tasks
    // require project membership.
    // ─────────────────────────────────────────────────────────────────────────
    public function delete(User $user, ProjectTask|Project $task): bool
    {
        if ($task instanceof Project) {
            return $user->can('manage_all_projects') || $user->can('delete_milestones');
        }

        return $this->hasScopedPermission($user, $task, 'delete_tasks');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // execute — can the user submit deliverables / update task execution state?
    //
    // STRICT: ONLY the explicitly assigned user (assignee_id) may execute a task.
    // Managers and global admins bypass this restriction.
    //
    // CRITICAL: This enforces that developers/QA can ONLY work on tasks that
    // are explicitly assigned to them. No one else can submit work on their behalf.
    // ─────────────────────────────────────────────────────────────────────────
    public function execute(User $user, ProjectTask $task): bool
    {
        if ($user->can('manage_all_projects')) {
            return true;
        }

        if (! $this->isProjectMember($user, $this->projectFor($task))) {
            return false;
        }

        return $user->can('manage_tasks') || $task->isAssignedTo($user);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // complete — can the user mark a task as DONE?
    //
    // complete — exact terminal transition capability.
    // ─────────────────────────────────────────────────────────────────────────
    public function complete(User $user, Project|ProjectTask $subject): bool
    {
        return $this->hasScopedPermission($user, $subject, 'complete_tasks');
    }

    public function reopen(User $user, ProjectTask $task): bool
    {
        if ($task->status !== ExecutionTaskStatus::DONE) {
            return false;
        }

        return $this->hasScopedPermission($user, $task, 'reopen_tasks');
    }

    public function changeStatus(User $user, ProjectTask $task, ?string $targetStatus = null): bool
    {
        if ($task->status === ExecutionTaskStatus::DONE || $targetStatus === ExecutionTaskStatus::DONE->value) {
            return false;
        }

        if ($this->hasScopedPermission($user, $task, 'change_task_status')) {
            return true;
        }

        if (! $this->isProjectMember($user, $this->projectFor($task))
            || ! $user->can('work_on_tasks')
            || ! $task->isAssignedTo($user)) {
            return false;
        }

        return $targetStatus === null || in_array($targetStatus, [
            ExecutionTaskStatus::BACKLOG->value,
            ExecutionTaskStatus::TO_DO->value,
            ExecutionTaskStatus::IN_PROGRESS->value,
            ExecutionTaskStatus::ON_HOLD->value,
            ExecutionTaskStatus::IN_REVIEW->value,
        ], true);
    }

    public function editDeliverable(User $user, ProjectTask $task): bool
    {
        return $this->hasScopedDeliverablePermission(
            $user,
            $task,
            'edit_project_task_deliverables'
        );
    }

    public function deleteDeliverable(User $user, ProjectTask $task): bool
    {
        return $this->hasScopedDeliverablePermission(
            $user,
            $task,
            'delete_project_task_deliverables'
        );
    }

    public function toggleDeliverable(User $user, ProjectTask $task): bool
    {
        if ($this->hasScopedDeliverablePermission(
            $user,
            $task,
            'toggle_project_task_deliverables'
        )) {
            return true;
        }

        return $this->isProjectMember($user, $this->projectFor($task))
            && $user->can('work_on_tasks')
            && $task->isAssignedTo($user);
    }

    /** @return array<string, bool> */
    public function allowedActions(User $user, ProjectTask $task): array
    {
        $canExecute = $this->execute($user, $task);

        return [
            'edit' => $this->update($user, $task),
            'delete' => $this->delete($user, $task),
            'execute' => $canExecute,
            'startTimer' => $canExecute
                && ($user->can('start_timer') || $user->can('manage_time_entries')),
            'stopTimer' => $canExecute
                && ($user->can('stop_timer') || $user->can('manage_time_entries')),
            'changeStatus' => $this->changeStatus($user, $task),
            'complete' => $task->status !== ExecutionTaskStatus::DONE && $this->complete($user, $task),
            'reopen' => $this->reopen($user, $task),
            'createDeliverable' => $this->createDeliverable($user, $task),
            'editDeliverable' => $this->editDeliverable($user, $task),
            'deleteDeliverable' => $this->deleteDeliverable($user, $task),
            'toggleDeliverable' => $this->toggleDeliverable($user, $task),
        ];
    }

    private function hasScopedPermission(User $user, Project|ProjectTask $subject, string $permission): bool
    {
        if ($user->can('manage_all_projects')) {
            return true;
        }

        $project = $this->projectFor($subject);

        return $this->isProjectMember($user, $project)
            && ($user->can('manage_tasks') || $user->can($permission));
    }

    private function hasScopedDeliverablePermission(User $user, Project|ProjectTask $subject, string $permission): bool
    {
        if ($user->can('manage_all_projects')) {
            return true;
        }

        $project = $this->projectFor($subject);

        return $this->isProjectMember($user, $project)
            && ($user->can('manage_tasks')
                || $user->can('manage_deliverables')
                || $user->can($permission));
    }

    private function projectFor(Project|ProjectTask $subject): Project
    {
        if ($subject instanceof Project) {
            return $subject;
        }

        if ($subject->relationLoaded('project')) {
            return $subject->getRelation('project');
        }

        return $this->projectCache[$subject->project_id]
            ??= Project::query()->findOrFail($subject->project_id);
    }

    private function isProjectMember(User $user, Project $project): bool
    {
        $key = "{$user->id}:{$project->id}";

        return $this->projectMembershipCache[$key]
            ??= $project->members()->where('user_id', $user->id)->exists();
    }
}
