<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTemplateStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_template_id',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'workflow_template_id');
    }
}
