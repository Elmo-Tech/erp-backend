<?php

namespace App\Models;

use App\Contracts\Trackable;
use App\Enums\TaskStatus;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Task extends Model implements Trackable
{
    use HasFactory, HasCreatedUpdatedBy;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'project_id',
        'parent_id',
        'assigned_to',
        'title',
        'description',
        'status',
        'estimated_hours',
        'total_time_spent',
        'internal_reviewer_notes',
        'reopen_count',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'estimated_hours' => 'decimal:2',
            'total_time_spent' => 'integer',
            'reopen_count' => 'integer',
        ];
    }

    /**
     * Get the project that owns this task.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the parent task (for recursive hierarchy).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    /**
     * Get the child tasks (for recursive hierarchy).
     */
    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    /**
     * Get the user assigned to this task.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get all time entries for this trackable entity.
     */
    public function timeEntries(): MorphMany
    {
        return $this->morphMany(TimeEntry::class, 'trackable');
    }

    /**
     * Get the display name for this trackable entity.
     */
    public function getTrackableDisplayName(): string
    {
        return $this->title;
    }

    /**
     * Get all ancestor tasks (parent, grandparent, etc.).
     *
     * @return Collection<int, Task>
     */
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

    /**
     * Get the direct time spent on this task (excluding children).
     */
    public function getDirectTimeSpent(): int
    {
        return (int) $this->timeEntries()
            ->whereNotNull('end_time')
            ->sum('duration_minutes');
    }
}
