<?php

namespace App\Models;

use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTestScenario extends Model
{
    use HasCreatedUpdatedBy, HasFactory;

    protected $fillable = [
        'task_id',
        'title',
        'description',
        'expected_result',
        'status',
        'order',
        'completed_by',
        'completed_at',
        'verified_by',
        'verified_at',
        'reviewer_notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Get the task.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the user who completed this scenario.
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Get the user who verified this scenario.
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Mark as completed by developer.
     */
    public function markAsCompleted(User $user): void
    {
        $this->update([
            'status' => 'passed',
            'completed_by' => $user->id,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark as verified by reviewer.
     */
    public function verify(User $reviewer, bool $passed, ?string $notes = null): void
    {
        $this->update([
            'status' => $passed ? 'passed' : 'failed',
            'verified_by' => $reviewer->id,
            'verified_at' => now(),
            'reviewer_notes' => $notes,
        ]);
    }

    /**
     * Check if scenario is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if scenario passed.
     */
    public function isPassed(): bool
    {
        return $this->status === 'passed';
    }

    /**
     * Check if scenario failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
