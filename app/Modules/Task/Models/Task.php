<?php

namespace App\Modules\Task\Models;

use App\Contracts\Trackable;
use App\Enums\DeliverableStatus;
use App\Enums\TaskCategory;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Modules\ProjectHandover\Models\CodeReview;
use App\Modules\Task\Models\Tag;
use App\Modules\TimeEntry\Models\TimeEntry;
use App\Modules\Settings\Models\User;
use App\Modules\Project\Models\Project;
use App\Traits\HasCreatedUpdatedBy;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model implements Trackable
{
    use HasCreatedUpdatedBy, HasFactory, SoftDeletes;

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
        'task_category',
        'due_date',
        'expected_resolution_date',
        'start_date',
        'sprint_number',
        'estimated_hours',
        'total_time_spent',
        'internal_reviewer_notes',
        'reopen_count',
        'completed_at',
        'delay_days',
        'recurring_config_id',
        'template_id',
        'occurrence_number',
        'is_recurring_instance',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'type' => TaskType::class,
            'priority' => TaskPriority::class,
            'task_category' => TaskCategory::class,
            'estimated_hours' => 'decimal:2',
            'total_time_spent' => 'integer',
            'reopen_count' => 'integer',
            'delay_days' => 'integer',
            'due_date' => 'date',
            'expected_resolution_date' => 'date',
            'start_date' => 'date',
            'completed_at' => 'datetime',
            'sprint_number' => 'integer',
            'occurrence_number' => 'integer',
            'is_recurring_instance' => 'boolean',
        ];
    }

    protected static function newFactory(): TaskFactory
    {
        return TaskFactory::new();
    }

    public function getMorphClass(): string
    {
        return static::class;
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

    public function primaryAssignees(): HasMany
    {
        return $this->assignees()->where('is_primary', true);
    }

    public function nonPrimaryAssignees(): HasMany
    {
        return $this->assignees()->where('is_primary', false);
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

    public function dependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'task_id');
    }

    public function blockedBy(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'depends_on_task_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class, 'template_id');
    }

    public function recurringConfig(): BelongsTo
    {
        return $this->belongsTo(RecurringTaskConfig::class, 'recurring_config_id');
    }

    public function codeReviews(): HasMany
    {
        return $this->hasMany(CodeReview::class);
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(TaskDeliverable::class)->orderBy('order')->orderBy('created_at');
    }

    public function pendingDeliverables(): HasMany
    {
        return $this->deliverables()->where('status', DeliverableStatus::PENDING);
    }

    public function submittedDeliverables(): HasMany
    {
        return $this->deliverables()->whereIn('status', [DeliverableStatus::SUBMITTED, DeliverableStatus::UNDER_REVIEW]);
    }

    public function approvedDeliverables(): HasMany
    {
        return $this->deliverables()->byStatus(DeliverableStatus::APPROVED);
    }

    public function dependentTasks(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'depends_on_task_id');
    }

    public function blockingTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'task_id', 'depends_on_task_id')
            ->withPivot(['dependency_type', 'lag_days'])
            ->withTimestamps();
    }

    public function blockedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'depends_on_task_id', 'task_id')
            ->withPivot(['dependency_type', 'lag_days'])
            ->withTimestamps();
    }

    public function recurringInstances(): HasMany
    {
        return $this->hasMany(Task::class, 'recurring_config_id');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(TaskChecklist::class)->orderBy('position');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(TaskLabel::class, 'task_label_task')->withTimestamps();
    }

    public function timeEstimates(): HasMany
    {
        return $this->hasMany(TaskTimeEstimate::class);
    }

    public function areDependenciesSatisfied(): bool
    {
        return $this->dependencies()->get()->every(fn ($dep) => $dep->isSatisfied());
    }

    public function getUnsatisfiedDependencies()
    {
        return $this->dependencies()->get()->filter(fn ($dep) => ! $dep->isSatisfied());
    }

    public function canStart(): bool
    {
        return $this->areDependenciesSatisfied();
    }

    public function getEarliestStartDate(): ?\Carbon\Carbon
    {
        $dates = $this->dependencies()
            ->get()
            ->map(fn ($dep) => $dep->getAvailableStartDate())
            ->filter();

        return $dates->isEmpty() ? null : $dates->max();
    }

    public function getChecklistCompletionAttribute(): float
    {
        $checklists = $this->checklists;
        if ($checklists->isEmpty()) {
            return 0;
        }

        $totalItems = $checklists->sum(fn ($list) => $list->items()->count());
        if ($totalItems === 0) {
            return 0;
        }

        $completedItems = $checklists->sum(fn ($list) => $list->items()->where('is_completed', true)->count());

        return round(($completedItems / $totalItems) * 100, 1);
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

    public function isPrimaryAssignee(User $user): bool
    {
        return $this->assignees()
            ->where('user_id', $user->id)
            ->where('is_primary', true)
            ->exists();
    }

    public function canEdit(User $user): bool
    {
        return $this->assignees()
            ->where('user_id', $user->id)
            ->whereIn('role', ['developer', 'lead'])
            ->exists();
    }

    public function getDeliveryStatus(): array
    {
        $status = [
            'isCompleted' => $this->status === TaskStatus::COMPLETED,
            'completedAt' => $this->completed_at,
            'dueDate' => $this->due_date,
            'delayDays' => $this->delay_days,
            'isOverdue' => false,
            'isEarly' => false,
            'isOnTime' => false,
            'statusText' => 'In Progress',
            'statusColor' => 'warning',
        ];

        if ($this->status === TaskStatus::COMPLETED) {
            if ($this->due_date) {
                $delayDays = $this->delay_days;

                if ($delayDays === null && $this->completed_at && $this->due_date) {
                    $dueDate = $this->due_date->startOfDay();
                    $completedDate = $this->completed_at->startOfDay();
                    $delayDays = (int) round($dueDate->diffInDays($completedDate, false));
                }

                if ($delayDays !== null) {
                    if ($delayDays > 0) {
                        $status['isOverdue'] = true;
                        $status['statusText'] = "Completed {$delayDays} days late";
                        $status['statusColor'] = 'destructive';
                        $status['delayDays'] = $delayDays;
                    } elseif ($delayDays < 0) {
                        $status['isEarly'] = true;
                        $status['statusText'] = 'Completed '.abs($delayDays).' days early';
                        $status['statusColor'] = 'success';
                        $status['delayDays'] = $delayDays;
                    } else {
                        $status['isOnTime'] = true;
                        $status['statusText'] = 'Completed on time';
                        $status['statusColor'] = 'success';
                        $status['delayDays'] = 0;
                    }
                } else {
                    $status['statusText'] = 'Completed';
                    $status['statusColor'] = 'success';
                }
            } else {
                $status['statusText'] = 'Completed (no due date)';
                $status['statusColor'] = 'success';
            }
        } elseif ($this->due_date && now()->isAfter($this->due_date)) {
            $dueDate = $this->due_date->startOfDay();
            $today = now()->startOfDay();
            $overdueDays = (int) round($dueDate->diffInDays($today));
            $status['isOverdue'] = true;
            $status['statusText'] = "Overdue by {$overdueDays} days";
            $status['statusColor'] = 'destructive';
        } elseif ($this->due_date) {
            $dueDate = $this->due_date->startOfDay();
            $today = now()->startOfDay();
            $remainingDays = (int) round($today->diffInDays($dueDate));
            if ($remainingDays <= 1) {
                $status['statusText'] = 'Due today';
                $status['statusColor'] = 'warning';
            } elseif ($remainingDays <= 3) {
                $status['statusText'] = "Due in {$remainingDays} days";
                $status['statusColor'] = 'warning';
            } else {
                $status['statusText'] = "Due in {$remainingDays} days";
                $status['statusColor'] = 'default';
            }
        }

        return $status;
    }

    public function isOverdue(): bool
    {
        return $this->due_date &&
            $this->status !== TaskStatus::COMPLETED &&
            now()->isAfter($this->due_date);
    }

    public function getDaysUntilDue(): ?int
    {
        if (! $this->due_date) {
            return null;
        }

        if ($this->status === TaskStatus::COMPLETED) {
            return $this->delay_days ? -$this->delay_days : null;
        }

        $dueDate = $this->due_date->startOfDay();
        $today = now()->startOfDay();

        if ($today->isAfter($dueDate)) {
            return (int) round($dueDate->diffInDays($today));
        } else {
            return (int) -round($today->diffInDays($dueDate));
        }
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

    public function canStartTimer(): bool
    {
        if ($this->status === TaskStatus::COMPLETED) {
            return false;
        }

        return ! $this->children()->exists();
    }

    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    public function scopeForUserSpecialization($query, User $user)
    {
        if (! $user->specialization) {
            return $query;
        }

        $compatibleCategories = TaskCategory::getCompatibleCategories($user->specialization);
        $categoryValues = array_map(fn ($category) => $category->value, $compatibleCategories);

        return $query->whereIn('task_category', $categoryValues);
    }

    public function scopeVisibleToUser($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->whereHas('assignees', function ($assigneeQuery) use ($user) {
                $assigneeQuery->where('user_id', $user->id);
            })
            ->orWhere(function ($compatibleQuery) use ($user) {
                if ($user->specialization) {
                    $compatibleCategories = TaskCategory::getCompatibleCategories($user->specialization);
                    $categoryValues = array_map(fn ($category) => $category->value, $compatibleCategories);

                    if (! empty($categoryValues)) {
                        $compatibleQuery->whereIn('task_category', $categoryValues);
                    }
                }
            })
            ->orWhereNull('task_category');
        });
    }

    public function scopeByCategory($query, TaskCategory $category)
    {
        return $query->where('task_category', $category->value);
    }
}
