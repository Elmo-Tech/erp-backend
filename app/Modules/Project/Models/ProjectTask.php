<?php

namespace App\Modules\Project\Models;

use App\Modules\Gamification\Models\PointTransaction;
use App\Modules\Project\Enums\ExecutionTaskStatus;
use App\Modules\Settings\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Execution-layer task for the Project module.
 *
 * This model uses the `project_tasks` table and is intentionally separate
 * from App\Modules\Task\Models\Task (which uses the `tasks` table and serves
 * a different bounded context).
 *
 * @property ExecutionTaskStatus $status
 * @property int $id
 * @property int $project_id
 * @property int|null $workflow_stage_id
 * @property int $pipeline_position
 * @property string|null $cover_image_path
 * @property-read Project|null $project
 * @property-read Collection<int, User> $assignees
 * @property-read ProjectWorkflowStage|null $workflowStage
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
        'cover_image_path',
        'status',
        'workflow_stage_id',
        'pipeline_position',
        'priority',
        'estimated_hours',
        'start_date',
        'due_date',
        'assignee_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExecutionTaskStatus::class,
            'estimated_hours' => 'decimal:2',
            'pipeline_position' => 'integer',
            'start_date' => 'date',
            'due_date' => 'date',
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
    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'project_task_assignees',
            'project_task_id',
            'user_id'
        )
            ->withPivot('position')
            ->withTimestamps()
            ->orderBy('project_task_assignees.position');
    }

    /** @return BelongsTo<ProjectWorkflowStage, $this> */
    public function workflowStage(): BelongsTo
    {
        return $this->belongsTo(ProjectWorkflowStage::class, 'workflow_stage_id');
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

    /**
     * Point transactions linked to this task (polymorphic source).
     */
    public function pointTransactions(): MorphMany
    {
        return $this->morphMany(PointTransaction::class, 'source');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Apply the execution-task visibility boundary for a caller.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('manage_all_projects') || $user->can('view_all_projects')) {
            return $query;
        }

        if (! $user->can('manage_tasks')) {
            $query->where(function (Builder $assigneeQuery) use ($user): void {
                $assigneeQuery
                    ->where('assignee_id', $user->id)
                    ->orWhereHas('assignees', fn (Builder $query): Builder => $query->whereKey($user->id));
            });
        }

        return $query->whereHas('project', function (Builder $projectQuery) use ($user): void {
            if ($user->can('access_client_portal')) {
                $projectQuery->where('client_id', $user->client()->value('id') ?? 0);

                return;
            }

            $projectQuery->whereHas(
                'members',
                fn (Builder $memberQuery) => $memberQuery->where('user_id', $user->id)
            );
        });
    }

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

    public function isAssignedTo(User $user): bool
    {
        if ((int) $this->assignee_id === $user->id) {
            return true;
        }

        if ($this->relationLoaded('assignees')) {
            return $this->assignees->contains('id', $user->id);
        }

        return $this->assignees()->whereKey($user->id)->exists();
    }

    /**
     * Total minutes logged against this task (completed entries only).
     */
    public function totalLoggedMinutes(): int
    {
        if ($this->relationLoaded('timeEntries')) {
            return (int) $this->timeEntries
                ->whereNotNull('end_time')
                ->sum('duration_minutes');
        }

        return (int) $this->timeEntries()->whereNotNull('end_time')->sum('duration_minutes');
    }

    // -------------------------------------------------------------------------
    // Deadline helpers (used by Gamification Engine)
    // -------------------------------------------------------------------------

    /**
     * Resolve the effective deadline for this task.
     *
     * Priority:
     *   1. task.due_date  (explicit task-level deadline)
     *   2. milestone.due_date (inherited from parent milestone)
     *   3. null (no deadline — gamification engine skips evaluation)
     */
    public function effectiveDueDate(): ?Carbon
    {
        if ($this->due_date !== null) {
            return Carbon::parse($this->due_date)->endOfDay();
        }

        if ($this->milestone_id !== null) {
            $milestone = $this->relationLoaded('milestone')
                ? $this->milestone
                : $this->milestone()->first();

            if ($milestone && $milestone->due_date !== null) {
                return Carbon::parse($milestone->due_date)->endOfDay();
            }
        }

        return null;
    }
}
