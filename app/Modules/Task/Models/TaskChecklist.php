<?php

namespace App\Modules\Task\Models;

use App\Modules\User\Models\User;
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

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class, 'checklist_id')->orderBy('position');
    }

    public function getCompletionPercentageAttribute(): float
    {
        $total = $this->items()->count();
        if ($total === 0) {
            return 0;
        }
        $completed = $this->items()->where('is_completed', true)->count();

        return round(($completed / $total) * 100, 1);
    }

    public function getCompletedCountAttribute(): int
    {
        return $this->items()->where('is_completed', true)->count();
    }

    public function getTotalCountAttribute(): int
    {
        return $this->items()->count();
    }

    public function isComplete(): bool
    {
        return $this->items()->exists() && $this->items()->where('is_completed', false)->doesntExist();
    }
}
