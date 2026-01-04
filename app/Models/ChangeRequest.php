<?php

namespace App\Models;

use App\Enums\ChangeRequestStatus;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeRequest extends Model
{
    use HasFactory, HasCreatedUpdatedBy;

    protected $fillable = [
        'cr_number',
        'project_id',
        'requested_by',
        'title',
        'description',
        'impact_analysis',
        'is_billable',
        'estimated_hours',
        'estimated_cost',
        'approved_cost',
        'additional_days',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_billable' => 'boolean',
            'estimated_hours' => 'decimal:2',
            'estimated_cost' => 'decimal:2',
            'approved_cost' => 'decimal:2',
            'additional_days' => 'integer',
            'status' => ChangeRequestStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Generate unique CR number
     */
    public static function generateCRNumber(int $projectId): string
    {
        $count = self::where('project_id', $projectId)->count() + 1;
        return sprintf('CR-%d-%03d', $projectId, $count);
    }
}
