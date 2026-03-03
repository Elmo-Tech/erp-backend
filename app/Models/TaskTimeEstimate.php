<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTimeEstimate extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'estimated_hours',
        'estimated_by',
        'notes',
    ];

    protected $casts = [
        'estimated_hours' => 'decimal:2',
    ];

    /**
     * Get the task this estimate belongs to
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the user who made this estimate
     */
    public function estimator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estimated_by');
    }
}
