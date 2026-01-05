<?php

namespace App\Models;

use App\Contracts\Trackable;
use App\Enums\ProjectStatus;
use App\Scopes\ClientScope;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Project extends Model implements Trackable
{
    use HasFactory, HasCreatedUpdatedBy;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
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
            'status' => ProjectStatus::class,
            'previous_status' => ProjectStatus::class,
            'budget' => 'decimal:2',
            'deadline' => 'date',
            'ui_approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Apply ClientScope when authenticated user is a client
        static::addGlobalScope(new ClientScope());
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
     */
    public function getProgressAttribute(): float
    {
        $totalTasks = $this->tasks()->count();
        if ($totalTasks === 0) {
            return 0;
        }
        
        $completedTasks = $this->tasks()->where('status', 'completed')->count();
        return round(($completedTasks / $totalTasks) * 100, 1);
    }

    /**
     * Get total time spent on project in minutes
     */
    public function getTotalTimeSpentAttribute(): int
    {
        return $this->tasks()->sum('total_time_spent') ?? 0;
    }

    /**
     * Get the display name for this trackable entity.
     */
    public function getTrackableDisplayName(): string
    {
        return $this->title;
    }
}
