<?php

namespace App\Models;

use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAssignee extends Model
{
    use HasFactory, HasCreatedUpdatedBy;

    protected $fillable = [
        'task_id',
        'user_id',
        'role',
        'is_primary',
        'points_earned',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'points_earned' => 'integer',
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
     * Get the assigned user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this assignee is a developer.
     */
    public function isDeveloper(): bool
    {
        return $this->role === 'developer';
    }

    /**
     * Check if this assignee is a reviewer.
     */
    public function isReviewer(): bool
    {
        return $this->role === 'reviewer';
    }

    /**
     * Check if this assignee is a lead.
     */
    public function isLead(): bool
    {
        return $this->role === 'lead';
    }

    /**
     * Add points to this assignee.
     */
    public function addPoints(int $points): void
    {
        $this->increment('points_earned', $points);
    }

    /**
     * Deduct points from this assignee.
     */
    public function deductPoints(int $points): void
    {
        $this->decrement('points_earned', $points);
    }
}
