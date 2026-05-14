<?php

namespace App\Modules\Project\Actions;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectHistory;
use BackedEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ProjectHistoryAction
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
            'old_value' => $this->historyValue($oldValue),
            'new_value' => $this->historyValue($newValue),
            'reason' => $reason,
            'changed_by' => Auth::id(),
        ]);
    }

    private function historyValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        return (string) $value;
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

        return $query->get();
    }
}
