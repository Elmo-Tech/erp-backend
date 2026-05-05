<?php

namespace App\Modules\Project\Models;

use App\Modules\Project\Enums\ExecutionTaskStatus;
use App\Modules\Settings\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Execution-layer task for the Project module.
 *
 * This model uses the `project_tasks` table and is intentionally separate
 * from App\Modules\Task\Models\Task (which uses the `tasks` table and serves
 * a different bounded context).
 */
class ProjectTask extends Model
{
    use SoftDeletes;

    protected $table = 'project_tasks';

    protected $fillable = [
        'project_id',
        'milestone_id',
        'parent_id',
        'title',
        'description',
        'status',
        'estimated_hours',
        'assignee_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExecutionTaskStatus::class,
            'estimated_hours' => 'decimal:2',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The project this task belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The milestone this task is grouped under.
     * Null means the task is in the project Backlog.
     */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    /**
     * Parent task (for subtasks).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Direct subtasks of this task.
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * The user assigned to this task.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Checklist-style deliverables attached to this task.
     */
    public function deliverables(): HasMany
    {
        return $this->hasMany(ProjectTaskDeliverable::class, 'task_id');
    }

    /**
     * Time entries logged against this task.
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(ProjectTimeEntry::class, 'task_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Tasks that are not assigned to any milestone (Backlog).
     */
    public function scopeBacklog($query)
    {
        return $query->whereNull('milestone_id');
    }

    /**
     * Root-level tasks only (no parent).
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isInBacklog(): bool
    {
        return $this->milestone_id === null;
    }

    public function isDone(): bool
    {
        return $this->status === ExecutionTaskStatus::DONE;
    }

    /**
     * Total minutes logged against this task (completed entries only).
     */
    public function totalLoggedMinutes(): int
    {
        return (int) $this->timeEntries()->whereNotNull('end_time')->sum('duration_minutes');
    }
}
