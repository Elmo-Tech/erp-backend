<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskChecklist extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'title',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    /**
     * Get the task this checklist belongs to
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the checklist items
     */
    public function items(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class, 'checklist_id')->orderBy('position');
    }

    /**
     * Get completion percentage
     */
    public function getCompletionPercentageAttribute(): float
    {
        $total = $this->items()->count();
        if ($total === 0) {
            return 0;
        }

        $completed = $this->items()->where('is_completed', true)->count();

        return round(($completed / $total) * 100, 1);
    }

    /**
     * Get completed items count
     */
    public function getCompletedCountAttribute(): int
    {
        return $this->items()->where('is_completed', true)->count();
    }

    /**
     * Get total items count
     */
    public function getTotalCountAttribute(): int
    {
        return $this->items()->count();
    }

    /**
     * Check if all items are completed
     */
    public function isComplete(): bool
    {
        return $this->items()->exists() && $this->items()->where('is_completed', false)->doesntExist();
    }
}
