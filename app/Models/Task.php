<?php

namespace App\Models;

use App\Models\CodeReview;
use App\Contracts\Trackable;
use App\Enums\TaskCategory;
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
        'task_category',
        'due_date',
        'start_date',
        'sprint_number',
        'estimated_hours',
        'total_time_spent',
        'internal_reviewer_notes',
        'reopen_count',
        'completed_at',
        'delay_days',
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
            'start_date' => 'date',
            'completed_at' => 'datetime',
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

    /**
     * Get delivery status information.
     */
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
            'statusColor' => 'warning'
        ];

        if ($this->status === TaskStatus::COMPLETED) {
            // Task is completed
            if ($this->due_date) {
                // Task has a due date, calculate delay if not already calculated
                $delayDays = $this->delay_days;
                
                // If delay_days is null but we have both due_date and completed_at, calculate it
                if ($delayDays === null && $this->completed_at && $this->due_date) {
                    $dueDate = $this->due_date->startOfDay();
                    $completedDate = $this->completed_at->startOfDay();
                    $delayDays = (int) $dueDate->diffInDays($completedDate, false);
                }
                
                if ($delayDays !== null) {
                    if ($delayDays > 0) {
                        $status['isOverdue'] = true;
                        $status['statusText'] = "Completed {$delayDays} days late";
                        $status['statusColor'] = 'destructive';
                        $status['delayDays'] = $delayDays;
                    } elseif ($delayDays < 0) {
                        $status['isEarly'] = true;
                        $status['statusText'] = "Completed " . abs($delayDays) . " days early";
                        $status['statusColor'] = 'success';
                        $status['delayDays'] = $delayDays;
                    } else {
                        $status['isOnTime'] = true;
                        $status['statusText'] = "Completed on time";
                        $status['statusColor'] = 'success';
                        $status['delayDays'] = 0;
                    }
                } else {
                    // Fallback if we still can't calculate delay
                    $status['statusText'] = "Completed";
                    $status['statusColor'] = 'success';
                }
            } else {
                // Task has no due date
                $status['statusText'] = "Completed (no due date)";
                $status['statusColor'] = 'success';
            }
        } elseif ($this->due_date && now()->isAfter($this->due_date)) {
            // Task is not completed but past due date
            $dueDate = $this->due_date->startOfDay();
            $today = now()->startOfDay();
            $overdueDays = (int) $dueDate->diffInDays($today);
            $status['isOverdue'] = true;
            $status['statusText'] = "Overdue by {$overdueDays} days";
            $status['statusColor'] = 'destructive';
        } elseif ($this->due_date) {
            // Task has due date but not overdue
            $dueDate = $this->due_date->startOfDay();
            $today = now()->startOfDay();
            $remainingDays = (int) $today->diffInDays($dueDate);
            if ($remainingDays <= 1) {
                $status['statusText'] = "Due today";
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
            $status['isOverdue'] = true;
            $status['statusText'] = "Overdue by {$overdueDays} days";
            $status['statusColor'] = 'destructive';
        } elseif ($this->due_date) {
            // Task has due date but not overdue
            $dueDate = $this->due_date->startOfDay();
            $today = now()->startOfDay();
            $remainingDays = (int) $today->diffInDays($dueDate);
            if ($remainingDays <= 1) {
                $status['statusText'] = "Due today";
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

    /**
     * Check if task is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date && 
               $this->status !== TaskStatus::COMPLETED && 
               now()->isAfter($this->due_date);
    }

    /**
     * Get days until due date (negative if overdue).
     */
    public function getDaysUntilDue(): ?int
    {
        if (!$this->due_date) {
            return null;
        }

        if ($this->status === TaskStatus::COMPLETED) {
            return $this->delay_days ? -$this->delay_days : null;
        }

        $dueDate = $this->due_date->startOfDay();
        $today = now()->startOfDay();
        
        if ($today->isAfter($dueDate)) {
            // Overdue - return positive number of days overdue
            return (int) $dueDate->diffInDays($today);
        } else {
            // Not due yet - return negative number of days until due
            return -(int) $today->diffInDays($dueDate);
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

    /**
     * Scope to filter tasks by user specialization compatibility
     */
    public function scopeForUserSpecialization($query, User $user)
    {
        if (!$user->specialization) {
            return $query; // No filtering if no specialization set
        }

        // Get compatible task categories for user's specialization
        $compatibleCategories = TaskCategory::getCompatibleCategories($user->specialization);
        $categoryValues = array_map(fn($category) => $category->value, $compatibleCategories);

        return $query->whereIn('task_category', $categoryValues);
    }

    /**
     * Scope to filter tasks that user can see (assigned to them or compatible with their specialization)
     */
    public function scopeVisibleToUser($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            // Tasks assigned to the user
            $q->whereHas('assignees', function ($assigneeQuery) use ($user) {
                $assigneeQuery->where('user_id', $user->id);
            })
            // OR tasks compatible with user's specialization
            ->orWhere(function ($compatibleQuery) use ($user) {
                if ($user->specialization) {
                    $compatibleCategories = TaskCategory::getCompatibleCategories($user->specialization);
                    $categoryValues = array_map(fn($category) => $category->value, $compatibleCategories);
                    
                    if (!empty($categoryValues)) {
                        $compatibleQuery->whereIn('task_category', $categoryValues);
                    }
                }
            })
            // OR tasks without category (general tasks visible to all)
            ->orWhereNull('task_category');
        });
    }

    /**
     * Scope to get tasks by category
     */
    public function scopeByCategory($query, TaskCategory $category)
    {
        return $query->where('task_category', $category->value);
    }
}
