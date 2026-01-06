<?php

namespace App\Models;

use App\Models\CodeReview;
use App\Contracts\Trackable;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\TaskPriority;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Task extends Model implements Trackable
{
    use HasFactory, HasCreatedUpdatedBy;

    protected $fillable = [
        'project_id',
        'parent_id',
        'assigned_to',
        'title',
        'description',
        'acceptance_criteria',
        'status',
        'type',
        'priority',
        'due_date',
        'start_date',
        'sprint_number',
        'estimated_hours',
        'total_time_spent',
        'internal_reviewer_notes',
        'reopen_count',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'type' => TaskType::class,
            'priority' => TaskPriority::class,
            'estimated_hours' => 'decimal:2',
            'total_time_spent' => 'integer',
            'reopen_count' => 'integer',
            'due_date' => 'date',
            'start_date' => 'date',
            'sprint_number' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    public function weeklyTasks(): HasMany
    {
        return $this->children()->where('type', TaskType::WEEKLY);
    }

    public function subtasks(): HasMany
    {
        return $this->children()->where('type', TaskType::SUBTASK);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(TaskAssignee::class);
    }

    public function developers(): HasMany
    {
        return $this->assignees()->where('role', 'developer');
    }

    public function reviewers(): HasMany
    {
        return $this->assignees()->where('role', 'reviewer');
    }

    public function leads(): HasMany
    {
        return $this->assignees()->where('role', 'lead');
    }

    public function primaryAssignee(): HasMany
    {
        return $this->assignees()->where('is_primary', true);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'task_tags')->withTimestamps();
    }

    public function testScenarios(): HasMany
    {
        return $this->hasMany(TaskTestScenario::class)->orderBy('order');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class)->orderByDesc('created_at');
    }

    public function publicActivities(): HasMany
    {
        return $this->activities()->where('is_internal', false);
    }

    public function timeEntries(): MorphMany
    {
        return $this->morphMany(TimeEntry::class, 'trackable');
    }

    public function codeReviews(): HasMany
    {
        return $this->hasMany(CodeReview::class);
    }

    public function getTrackableDisplayName(): string
    {
        return $this->title;
    }

    public function getAllAncestors(): Collection
    {
        $ancestors = collect();
        $current = $this;

        while ($current->parent_id !== null) {
            $current = $current->parent;
            if ($current) {
                $ancestors->push($current);
            } else {
                break;
            }
        }

        return $ancestors;
    }

    public function getDirectTimeSpent(): int
    {
        return (int) $this->timeEntries()
            ->whereNotNull('end_time')
            ->sum('duration_minutes');
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assignees()->where('user_id', $user->id)->exists();
    }

    public function isDeveloper(User $user): bool
    {
        return $this->assignees()
            ->where('user_id', $user->id)
            ->where('role', 'developer')
            ->exists();
    }

    public function isReviewer(User $user): bool
    {
        return $this->assignees()
            ->where('user_id', $user->id)
            ->where('role', 'reviewer')
            ->exists();
    }

    public function canEdit(User $user): bool
    {
        return $this->assignees()
            ->where('user_id', $user->id)
            ->whereIn('role', ['developer', 'lead'])
            ->exists();
    }

    public function getTestCompletionPercentage(): float
    {
        $total = $this->testScenarios()->count();
        if ($total === 0) {
            return 100.0;
        }
        $passed = $this->testScenarios()->where('status', 'passed')->count();
        return round(($passed / $total) * 100, 2);
    }

    public function allTestsPassed(): bool
    {
        $total = $this->testScenarios()->count();
        if ($total === 0) {
            return true;
        }
        return $this->testScenarios()->where('status', 'passed')->count() === $total;
    }

    public function scopeMain($query)
    {
        return $query->where('type', TaskType::MAIN);
    }

    public function scopeWeekly($query)
    {
        return $query->where('type', TaskType::WEEKLY);
    }

    public function scopeSubtasks($query)
    {
        return $query->where('type', TaskType::SUBTASK);
    }

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Check if timer can be started on this task.
     * Timer is only allowed on leaf tasks (tasks with no children) and not completed tasks.
     *
     * @return bool
     */
    public function canStartTimer(): bool
    {
        // Cannot start timer on completed tasks
        if ($this->status === TaskStatus::COMPLETED) {
            return false;
        }
        
        // Timer only allowed on leaf tasks (no children)
        return !$this->children()->exists();
    }

    /**
     * Check if this task has any children.
     *
     * @return bool
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }
}
