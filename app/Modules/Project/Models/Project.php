<?php

namespace App\Modules\Project\Models;

use App\Enums\ProjectRole;
use App\Modules\Client\Models\Client;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Project\Enums\ProjectStatus;
use App\Modules\Settings\Models\User;
use App\Modules\Task\Models\Task;
use App\Scopes\ClientScope;
use App\Traits\HasCreatedUpdatedBy;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_id
 * @property string $title
 * @property ProjectStatus|null $status
 * @property float|int|string|null $budget
 * @property Carbon|null $deadline
 * @property Carbon|null $ui_approved_at
 * @property Carbon|null $created_at
 * @property-read Client $client
 * @property-read Collection<int, Invoice> $invoices
 * @property-read Collection<int, ProjectMember> $members
 * @property-read Collection<int, ProjectTask> $executionTasks
 * @property-read Collection<int, ProjectWorkflowStage> $workflowStages
 */
class Project extends Model
{
    use HasCreatedUpdatedBy, HasFactory, SoftDeletes;

    protected $table = 'projects';

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

    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }

    protected static function booted(): void
    {
        // Apply ClientScope when authenticated user is a client
        static::addGlobalScope(new ClientScope);
    }

    /**
     * Get the client that owns this project.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
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

    public function wikis(): HasMany
    {
        return $this->hasMany(ProjectWiki::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function invoices(): MorphMany
    {
        return $this->morphMany(Invoice::class, 'model');
    }

    public function pinnedByUsers()
    {
        return $this->belongsToMany(User::class, 'pinned_projects')
            ->withTimestamps();
    }

    public function isPinnedBy(?int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        return $this->pinnedByUsers()->where('user_id', $userId)->exists();
    }

    // =========================================================================
    // Project Execution Layer — appended relationships
    // The relationships below are part of the execution layer scaffold.
    // They do NOT replace or modify any existing relationship above.
    // =========================================================================

    /**
     * Milestones for this project.
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    /**
     * Execution-layer tasks (project_tasks table).
     * Distinct from tasks() which points to App\Modules\Task\Models\Task.
     */
    /** @return HasMany<ProjectTask, $this> */
    public function executionTasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    /** @return HasMany<ProjectWorkflowStage, $this> */
    public function workflowStages(): HasMany
    {
        return $this->hasMany(ProjectWorkflowStage::class)->orderBy('position');
    }

    /**
     * Change requests raised against this project.
     */
    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class);
    }

    /**
     * Team members assigned to this project via the project_members pivot.
     * Uses ProjectRole enum for the 'role' pivot column.
     */
    public function teamMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members', 'project_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps()
            ->using(ProjectMemberPivot::class);
    }
}
