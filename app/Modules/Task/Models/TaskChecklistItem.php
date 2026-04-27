<?php

namespace App\Modules\Task\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'checklist_id',
        'content',
        'is_completed',
        'completed_by',
        'completed_at',
        'position',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'position' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(TaskChecklist::class, 'checklist_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function markComplete(int $userId): void
    {
        $this->update([
            'is_completed' => true,
            'completed_by' => $userId,
            'completed_at' => now(),
        ]);
    }

    public function markIncomplete(): void
    {
        $this->update([
            'is_completed' => false,
            'completed_by' => null,
            'completed_at' => null,
        ]);
    }

    public function toggle(int $userId): bool
    {
        if ($this->is_completed) {
            $this->markIncomplete();

            return false;
        } else {
            $this->markComplete($userId);

            return true;
        }
    }
}
