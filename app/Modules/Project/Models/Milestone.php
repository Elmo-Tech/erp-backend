<?php

namespace App\Modules\Project\Models;

use App\Enums\MilestoneStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Milestone extends Model
{
    use SoftDeletes;

    protected $table = 'milestones';

    protected $fillable = [
        'project_id',
        'title',
        'due_date',
        'status',
        'progress_percentage',
        'payment_stage_id',
        'invoice_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => MilestoneStatus::class,
            'progress_percentage' => 'integer',
            'due_date' => 'date',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The project this milestone belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * All execution-layer tasks grouped under this milestone.
     * Tasks with milestone_id = null are in the Backlog.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'milestone_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Recalculate and persist progress_percentage based on done tasks.
     */
    public function recalculateProgress(): void
    {
        $total = $this->tasks()->count();

        if ($total === 0) {
            $this->update(['progress_percentage' => 0]);

            return;
        }

        $done = $this->tasks()->where('status', 'done')->count();
        $this->update(['progress_percentage' => (int) round(($done / $total) * 100)]);
    }

    public function isCompleted(): bool
    {
        return $this->status === MilestoneStatus::COMPLETED;
    }
}
