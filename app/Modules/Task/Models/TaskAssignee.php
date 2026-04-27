<?php

namespace App\Modules\Task\Models;

use App\Models\User;
use App\Traits\HasCreatedUpdatedBy;
use Database\Factories\TaskAssigneeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAssignee extends Model
{
    use HasCreatedUpdatedBy, HasFactory;

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

    protected static function newFactory(): TaskAssigneeFactory
    {
        return TaskAssigneeFactory::new();
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDeveloper(): bool
    {
        return $this->role === 'developer';
    }

    public function isReviewer(): bool
    {
        return $this->role === 'reviewer';
    }

    public function isLead(): bool
    {
        return $this->role === 'lead';
    }

    public function addPoints(int $points): void
    {
        $this->increment('points_earned', $points);
    }

    public function deductPoints(int $points): void
    {
        $this->decrement('points_earned', $points);
    }
}
