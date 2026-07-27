<?php

namespace App\Modules\Project\Models;

use App\Modules\Project\Enums\ExecutionTaskStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property ExecutionTaskStatus $status
 * @property string $color
 * @property int $position
 * @property bool $is_terminal
 * @property-read Project $project
 * @property-read Collection<int, ProjectTask> $tasks
 */
class ProjectWorkflowStage extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'status',
        'color',
        'position',
        'is_terminal',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExecutionTaskStatus::class,
            'position' => 'integer',
            'is_terminal' => 'boolean',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<ProjectTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'workflow_stage_id');
    }
}
