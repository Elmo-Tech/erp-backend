<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTimeEntry;
use App\Modules\Settings\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectTimeEntryPolicy
{
    use HandlesAuthorization;

    public function update(User $user, ProjectTimeEntry $entry): bool
    {
        return $this->canManageEntry($user, $entry, 'edit_time_entries');
    }

    public function delete(User $user, ProjectTimeEntry $entry): bool
    {
        return $this->canManageEntry($user, $entry, 'delete_time_entries');
    }

    private function canManageEntry(User $user, ProjectTimeEntry $entry, string $permission): bool
    {
        if ($user->can('manage_all_projects') || $user->can('manage_time_entries')) {
            return true;
        }

        if (! $user->can($permission)) {
            return false;
        }

        if ((int) $entry->user_id === $user->id) {
            return true;
        }

        $entry->loadMissing('task.project');

        return $this->isProjectManager($user, $entry->task?->project);
    }

    private function isProjectManager(User $user, ?Project $project): bool
    {
        if ($project === null) {
            return false;
        }

        $member = $project->members()->where('user_id', $user->id)->first();

        if ($member === null) {
            return false;
        }

        $role = $member->role instanceof ProjectRole ? $member->role->value : (string) $member->role;

        return in_array($role, [
            ProjectRole::MANAGER->value,
            ProjectRole::TECH_LEAD->value,
        ], true);
    }
}
