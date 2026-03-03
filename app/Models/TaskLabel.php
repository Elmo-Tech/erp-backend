<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TaskLabel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'color',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get tasks with this label
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_label_task');
    }

    /**
     * Scope for active labels
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get usage count
     */
    public function getUsageCountAttribute(): int
    {
        return $this->tasks()->count();
    }
}
