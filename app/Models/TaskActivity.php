<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'user_id',
        'type',
        'description',
        'metadata',
        'comment',
        'is_internal',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_internal' => 'boolean',
        ];
    }

    // Activity types
    public const TYPE_CREATED = 'created';

    public const TYPE_UPDATED = 'updated';

    public const TYPE_STATUS_CHANGE = 'status_change';

    public const TYPE_ASSIGNEE_ADDED = 'assignee_added';

    public const TYPE_ASSIGNEE_REMOVED = 'assignee_removed';

    public const TYPE_TIMER_START = 'timer_start';

    public const TYPE_TIMER_STOP = 'timer_stop';

    public const TYPE_TEST_COMPLETED = 'test_completed';

    public const TYPE_TEST_VERIFIED = 'test_verified';

    public const TYPE_TEST_FAILED = 'test_failed';

    public const TYPE_COMMENT = 'comment';

    public const TYPE_INTERNAL_NOTE = 'internal_note';

    public const TYPE_TAG_ADDED = 'tag_added';

    public const TYPE_TAG_REMOVED = 'tag_removed';

    public const TYPE_PRIORITY_CHANGE = 'priority_change';

    public const TYPE_DUE_DATE_CHANGE = 'due_date_change';

    public const TYPE_DELIVERABLE_SUBMITTED = 'deliverable_submitted';

    public const TYPE_DELIVERABLE_APPROVED = 'deliverable_approved';

    public const TYPE_DELIVERABLE_REJECTED = 'deliverable_rejected';

    /**
     * Get the task.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the user who performed this activity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create a status change activity.
     */
    public static function logStatusChange(Task $task, User $user, string $oldStatus, string $newStatus, ?string $reason = null): self
    {
        $description = "Changed status from {$oldStatus} to {$newStatus}";
        if ($reason) {
            $description .= " ({$reason})";
        }

        return self::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'type' => self::TYPE_STATUS_CHANGE,
            'description' => $description,
            'metadata' => [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
            ],
        ]);
    }

    /**
     * Create a timer start activity.
     */
    public static function logTimerStart(Task $task, User $user, int $timeEntryId): self
    {
        return self::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'type' => self::TYPE_TIMER_START,
            'description' => 'Started timer',
            'metadata' => ['time_entry_id' => $timeEntryId],
        ]);
    }

    /**
     * Create a timer stop activity.
     */
    public static function logTimerStop(Task $task, User $user, int $timeEntryId, int $durationMinutes): self
    {
        $hours = floor($durationMinutes / 60);
        $minutes = $durationMinutes % 60;
        $duration = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";

        return self::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'type' => self::TYPE_TIMER_STOP,
            'description' => "Stopped timer ({$duration})",
            'metadata' => [
                'time_entry_id' => $timeEntryId,
                'duration_minutes' => $durationMinutes,
            ],
        ]);
    }

    /**
     * Create a comment activity.
     */
    public static function logComment(Task $task, User $user, string $comment, bool $isInternal = false): self
    {
        return self::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'type' => $isInternal ? self::TYPE_INTERNAL_NOTE : self::TYPE_COMMENT,
            'description' => $isInternal ? 'Added internal note' : 'Added comment',
            'comment' => $comment,
            'is_internal' => $isInternal,
        ]);
    }

    /**
     * Create an assignee added activity.
     */
    public static function logAssigneeAdded(Task $task, User $performer, User $assignee, string $role): self
    {
        return self::create([
            'task_id' => $task->id,
            'user_id' => $performer->id,
            'type' => self::TYPE_ASSIGNEE_ADDED,
            'description' => "Added {$assignee->name} as {$role}",
            'metadata' => [
                'assignee_id' => $assignee->id,
                'assignee_name' => $assignee->name,
                'role' => $role,
            ],
        ]);
    }

    /**
     * Scope to exclude internal activities.
     */
    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }

    /**
     * Scope to get only comments.
     */
    public function scopeComments($query)
    {
        return $query->whereIn('type', [self::TYPE_COMMENT, self::TYPE_INTERNAL_NOTE]);
    }

    /**
     * Log deliverable submission.
     */
    public static function logDeliverableSubmitted(Task $task, User $developer, string $deliverableTitle): self
    {
        return self::create([
            'task_id' => $task->id,
            'user_id' => $developer->id,
            'type' => self::TYPE_DELIVERABLE_SUBMITTED,
            'description' => "Submitted deliverable: {$deliverableTitle}",
            'metadata' => [
                'deliverable_title' => $deliverableTitle,
            ],
        ]);
    }

    /**
     * Log deliverable approval with points.
     */
    public static function logDeliverableApproved(
        Task $task,
        User $reviewer,
        string $deliverableTitle,
        int $pointsAwarded,
        bool $isBonus = false
    ): self {
        $bonusText = $isBonus ? ' (Bonus)' : '';
        $description = "Approved deliverable: {$deliverableTitle}{$bonusText} (+{$pointsAwarded} points)";

        return self::create([
            'task_id' => $task->id,
            'user_id' => $reviewer->id,
            'type' => self::TYPE_DELIVERABLE_APPROVED,
            'description' => $description,
            'metadata' => [
                'deliverable_title' => $deliverableTitle,
                'points_awarded' => $pointsAwarded,
                'is_bonus' => $isBonus,
            ],
        ]);
    }

    /**
     * Log deliverable rejection with points deduction.
     */
    public static function logDeliverableRejected(
        Task $task,
        User $reviewer,
        string $deliverableTitle,
        int $pointsDeducted,
        ?string $reason = null
    ): self {
        $description = "Rejected deliverable: {$deliverableTitle} (-{$pointsDeducted} points)";

        return self::create([
            'task_id' => $task->id,
            'user_id' => $reviewer->id,
            'type' => self::TYPE_DELIVERABLE_REJECTED,
            'description' => $description,
            'metadata' => [
                'deliverable_title' => $deliverableTitle,
                'points_deducted' => $pointsDeducted,
                'reason' => $reason,
            ],
        ]);
    }
}
