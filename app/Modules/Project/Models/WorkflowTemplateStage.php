<?php

namespace App\Modules\Project\Models;

use App\Modules\Project\Enums\ExecutionTaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workflow_template_id
 * @property string $name
 * @property ExecutionTaskStatus $status
 * @property string $color
 * @property int $position
 * @property bool $is_terminal
 */
class WorkflowTemplateStage extends Model
{
    protected $fillable = [
        'workflow_template_id',
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

    /** @return BelongsTo<WorkflowTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'workflow_template_id');
    }
}
