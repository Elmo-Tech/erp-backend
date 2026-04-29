<?php

namespace App\Modules\Task\Models;

use App\Modules\User\Models\User;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskDependency extends Model
{
    use HasCreatedUpdatedBy, HasFactory;

    protected $fillable = [
        'task_id',
        'depends_on_task_id',
        'dependency_type',
        'lag_days',
        'is_escalated',
        'escalated_by',
        'escalated_at',
        'resolved_at',
    ];

    protected $casts = [
        'lag_days' => 'integer',
        'is_escalated' => 'boolean',
        'escalated_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    const TYPE_FINISH_TO_START = 'finish_to_start';
    const TYPE_START_TO_START = 'start_to_start';
    const TYPE_FINISH_TO_FINISH = 'finish_to_finish';
    const TYPE_START_TO_FINISH = 'start_to_finish';

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function dependsOnTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'depends_on_task_id');
    }

    public function escalatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_by');
    }

    public function isSatisfied(): bool
    {
        $dependentTask = $this->dependsOnTask;

        return match ($this->dependency_type) {
            self::TYPE_FINISH_TO_START => $dependentTask->status->isCompleted(),
            self::TYPE_START_TO_START => $dependentTask->start_date?->isPast() ?? false,
            self::TYPE_FINISH_TO_FINISH => $dependentTask->status->isCompleted(),
            self::TYPE_START_TO_FINISH => $dependentTask->start_date?->isPast() ?? false,
            default => false,
        };
    }

    public function getAvailableStartDate(): ?\Carbon\Carbon
    {
        $dependentTask = $this->dependsOnTask;
        $lagDays = $this->lag_days ?? 0;

        return match ($this->dependency_type) {
            self::TYPE_FINISH_TO_START => $dependentTask->completed_at?->addDays($lagDays),
            self::TYPE_START_TO_START => $dependentTask->start_date?->addDays($lagDays),
            self::TYPE_FINISH_TO_FINISH => $dependentTask->completed_at?->addDays($lagDays),
            self::TYPE_START_TO_FINISH => $dependentTask->start_date?->addDays($lagDays),
            default => null,
        };
    }

    public function scopeBlocking($query)
    {
        return $query->where('dependency_type', self::TYPE_FINISH_TO_START);
    }
}
