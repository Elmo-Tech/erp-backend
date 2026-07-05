<?php

namespace App\Models;

use App\Casts\DynamicStatusCast;
use App\Contracts\Trackable;
use App\Enums\ProjectStatus;
use App\Scopes\ClientScope;
use App\Scopes\TeamMemberScope;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property int $client_id
 * @property string $title
 * @property ProjectStatus|null $status
 * @property float|int|string|null $budget
 * @property \Illuminate\Support\Carbon|null $deadline
 * @property \Illuminate\Support\Carbon|null $ui_approved_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read Client $client
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Invoice> $invoices
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProjectMember> $members
 */
class Project extends Model implements Trackable
{
    use HasCreatedUpdatedBy, HasFactory, SoftDeletes;

    /**
     * Fields whose history was recorded before saving this model instance.
     *
     * @var array<int, string>
     */
    public array $skipHistoryFields = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'quotation_id',
        'title',
        'description',
        'status',
        'previous_status',
        'budget',
        'deadline',
        'ui_approved_at',
        'auto_approval_job_id',
        'google_drive_folder_id',
        'google_drive_folder_name',
        'google_drive_folder_url',
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
            'status' => DynamicStatusCast::class . ':project_status',
            'previous_status' => DynamicStatusCast::class . ':project_status',
            'budget' => 'decimal:2',
            'deadline' => 'date',
            'ui_approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Apply ClientScope when authenticated user is a client
        static::addGlobalScope(new ClientScope);

        // Apply TeamMemberScope for employees to only see their projects
        static::addGlobalScope(new TeamMemberScope);
    }

    /**
     * Get the client that owns this project.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the quotation that this project was converted from.
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * Get all history records for this project.
     */
    public function histories(): HasMany
    {
        return $this->hasMany(ProjectHistory::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get all wiki entries for this project.
     */
    public function wikis(): HasMany
    {
        return $this->hasMany(ProjectWiki::class);
    }

    /**
     * Get all invoices for this project.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get all tasks for this project.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get all files for this project.
     */
    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    /**
     * Get all members for this project.
     */
    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /**
     * Get all time entries for this trackable entity.
     */
    public function timeEntries(): MorphMany
    {
        return $this->morphMany(TimeEntry::class, 'trackable');
    }

    /**
     * Calculate project progress based on completed tasks
     * Cached for 5 minutes to improve performance
     */
    public function getProgressAttribute(): float
    {
        return Cache::remember(
            "project_{$this->id}_progress",
            now()->addMinutes(5),
            function () {
                $totalTasks = $this->tasks()->count();
                if ($totalTasks === 0) {
                    return 0.0;
                }

                $completedTasks = $this->tasks()->where('status', 'completed')->count();

                return round(($completedTasks / $totalTasks) * 100, 1);
            }
        );
    }

    /**
     * Clear the cached progress value
     * Call this when tasks are added, removed, or status changes
     */
    public function clearProgressCache(): void
    {
        Cache::forget("project_{$this->id}_progress");
    }

    /**
     * Get cached total time spent or calculate and cache it
     */
    public function getCachedTotalTimeSpent(): int
    {
        return Cache::remember(
            "project_{$this->id}_total_time",
            now()->addMinutes(5),
            fn () => $this->getTotalTimeSpentAttribute()
        );
    }

    /**
     * Clear all project-related caches
     */
    public function clearAllCaches(): void
    {
        Cache::forget("project_{$this->id}_progress");
        Cache::forget("project_{$this->id}_total_time");
    }

    /**
     * Get total time spent on project in minutes
     * Only counts leaf tasks (subtasks) to avoid duplication
     * since parent tasks inherit time from children
     */
    public function getTotalTimeSpentAttribute(): int
    {
        // Get time from leaf tasks only (tasks without children)
        // This avoids counting the same time multiple times
        // since parent tasks aggregate time from their children
        return $this->tasks()
            ->whereDoesntHave('children')
            ->sum('total_time_spent');
    }

    /**
     * Get the display name for this trackable entity.
     */
    public function getTrackableDisplayName(): string
    {
        return $this->title;
    }

    /**
     * Get the users who have pinned this project
     */
    public function pinnedByUsers()
    {
        return $this->belongsToMany(User::class, 'pinned_projects')
            ->withTimestamps();
    }

    /**
     * Check if this project is pinned by a specific user
     */
    public function isPinnedBy(?int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        return $this->pinnedByUsers()->where('user_id', $userId)->exists();
    }
}
