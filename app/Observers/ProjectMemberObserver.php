<?php

namespace App\Observers;

use App\Modules\Project\Models\ProjectHistory;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Settings\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * ProjectMemberObserver — records project history entries whenever a team
 * member is added to or removed from a project.
 *
 * All entries land in the `project_histories` table and are surfaced by the
 * ProjectHistoryController / ActivityTab on the frontend.
 */
class ProjectMemberObserver
{
    public function created(ProjectMember $member): void
    {
        $userName = User::find($member->user_id)?->name ?? "User #{$member->user_id}";
        $role = $member->role->label();

        $this->record(
            $member->project_id,
            'member_added',
            '',
            $userName,
            "{$userName} was added to the project as {$role}"
        );
    }

    public function updated(ProjectMember $member): void
    {
        $dirty = $member->getDirty();

        if (isset($dirty['role'])) {
            $userName = User::find($member->user_id)?->name ?? "User #{$member->user_id}";
            $oldRole = $member->getOriginal('role');
            $newRole = $dirty['role'];

            $this->record(
                $member->project_id,
                'member_role_changed',
                is_object($oldRole) ? $oldRole->value : (string) $oldRole,
                is_object($newRole) ? $newRole->value : (string) $newRole,
                "{$userName}'s project role was changed"
            );
        }
    }

    public function deleted(ProjectMember $member): void
    {
        $userName = User::find($member->user_id)?->name ?? "User #{$member->user_id}";

        $this->record(
            $member->project_id,
            'member_removed',
            $userName,
            '',
            "{$userName} was removed from the project"
        );
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function record(
        int $projectId,
        string $fieldName,
        string $oldValue,
        string $newValue,
        string $reason
    ): void {
        ProjectHistory::create([
            'project_id' => $projectId,
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => $reason,
            'changed_by' => Auth::id(),
        ]);
    }
}
