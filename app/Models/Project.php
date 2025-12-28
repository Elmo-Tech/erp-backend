<?php

namespace App\Models;

use App\Contracts\Trackable;
use App\Enums\ProjectStatus;
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

    /**
     * Get the client (user) that owns this project.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
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
}
