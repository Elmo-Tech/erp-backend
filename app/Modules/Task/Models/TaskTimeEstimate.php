<?php

namespace App\Modules\Task\Models;

use App\Models\User;
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

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function estimator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estimated_by');
    }
}
