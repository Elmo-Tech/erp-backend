<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectWorkflowStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'workflow_template_stage_id',
        'name',
        'slug',
        'color',
        'category',
        'position',
        'is_terminal',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_terminal' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'workflow_stage_id')
            ->where('is_pipeline_task', true)
            ->orderBy('pipeline_position');
    }
}
