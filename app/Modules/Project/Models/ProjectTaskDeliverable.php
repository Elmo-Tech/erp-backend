<?php

namespace App\Modules\Project\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Checklist-style deliverable attached to a ProjectTask.
 */
class ProjectTaskDeliverable extends Model
{
    use SoftDeletes;

    protected $table = 'project_task_deliverables';

    protected $fillable = [
        'task_id',
        'title',
        'is_completed',
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The execution-layer task this deliverable belongs to.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }
}
