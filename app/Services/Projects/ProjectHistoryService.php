<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectHistory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ProjectHistoryService
{
    /**
     * Fields that require a reason when changed.
     * These are critical fields where changes must be justified.
     */
    private const TRACKED_FIELDS = ['deadline', 'budget'];

    /**
     * Record a change to a project field.
     *
     * @param  Project  $project  The project being modified
     * @param  string  $fieldName  The name of the field that changed
     * @param  mixed  $oldValue  The previous value of the field
     * @param  mixed  $newValue  The new value of the field
     * @param  string|null  $reason  Optional reason for the change (required for tracked fields)
     * @return ProjectHistory The created history record
     */
    public function recordChange(
        Project $project,
        string $fieldName,
        mixed $oldValue,
        mixed $newValue,
        ?string $reason = null
    ): ProjectHistory {
        return ProjectHistory::create([
            'project_id' => $project->id,
            'field_name' => $fieldName,
            'old_value' => is_null($oldValue) ? null : (string) $oldValue,
            'new_value' => is_null($newValue) ? null : (string) $newValue,
            'reason' => $reason,
            'changed_by' => Auth::id(),
        ]);
    }

    /**
     * Check if a field requires a reason for changes.
     * Deadline and budget changes require justification for audit purposes.
     *
     * @param  string  $fieldName  The field name to check
     * @return bool True if the field requires a reason when changed
     */
    public function requiresReason(string $fieldName): bool
    {
        return in_array($fieldName, self::TRACKED_FIELDS);
    }

    /**
     * Get the list of fields that require a reason when changed.
     *
     * @return array<string> List of tracked field names
     */
    public function getTrackedFields(): array
    {
        return self::TRACKED_FIELDS;
    }

    /**
     * Get history for a project.
     * Returns all history records ordered by most recent first,
     * with the user who made each change eager loaded.
     *
     * @param  Project  $project  The project to get history for
     * @param  string|null  $fieldFilter  Optional filter by field name
     * @return Collection<ProjectHistory> Collection of history records
     */
    public function getHistory(Project $project, ?string $fieldFilter = null): Collection
    {
        $query = $project->histories()
            ->with('changedByUser')
            ->orderBy('created_at', 'desc');

        if ($fieldFilter !== null) {
            $query->where('field_name', $fieldFilter);
        }

        return $query->get()
            ->merge($this->currentActivityHistory($project, $fieldFilter))
            ->sortByDesc('created_at')
            ->values();
    }

    private function currentActivityHistory(Project $project, ?string $fieldFilter = null): Collection
    {
        $project->loadMissing(['members.user', 'files.createdBy', 'updatedBy']);

        return collect()
            ->merge($this->memberHistory($project))
            ->merge($this->fileHistory($project))
            ->merge($this->driveHistory($project))
            ->when($fieldFilter !== null, fn ($items) => $items->where('field_name', $fieldFilter)->values());
    }

    private function memberHistory(Project $project): Collection
    {
        return $project->members->map(function ($member) use ($project) {
            return $this->syntheticHistory(
                id: 400000 + $member->id,
                project: $project,
                field: 'team_member',
                value: trim(($member->user?->name ?? 'Unknown user').' ('.$member->role.')'),
                createdAt: $member->created_at,
            );
        });
    }

    private function fileHistory(Project $project): Collection
    {
        return $project->files->map(function ($file) use ($project) {
            return $this->syntheticHistory(
                id: 500000 + $file->id,
                project: $project,
                field: 'project_file',
                value: $file->name,
                createdAt: $file->created_at,
                changedBy: $file->created_by,
                changedByUser: $file->createdBy,
            );
        });
    }

    private function driveHistory(Project $project): Collection
    {
        if (! $project->google_drive_folder_id) {
            return collect();
        }

        return collect([
            $this->syntheticHistory(
                id: 600000 + $project->id,
                project: $project,
                field: 'google_drive_folder',
                value: $project->google_drive_folder_name ?? $project->google_drive_folder_id,
                createdAt: $project->updated_at,
                changedBy: $project->updated_by,
                changedByUser: $project->updatedBy,
            ),
        ]);
    }

    private function syntheticHistory(int $id, Project $project, string $field, string $value, mixed $createdAt, ?int $changedBy = null, mixed $changedByUser = null): ProjectHistory
    {
        $history = new ProjectHistory([
            'project_id' => $project->id,
            'field_name' => $field,
            'old_value' => null,
            'new_value' => $value,
            'changed_by' => $changedBy,
        ]);
        $history->id = $id;
        $history->created_at = $createdAt;
        $history->updated_at = $createdAt;
        if ($changedByUser) {
            $history->setRelation('changedByUser', $changedByUser);
        }

        return $history;
    }
}
