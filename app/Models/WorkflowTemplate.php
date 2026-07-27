<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<WorkflowTemplateStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(WorkflowTemplateStage::class)
            ->orderBy('position');
    }
}
