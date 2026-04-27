<?php

namespace App\Models;

use App\Modules\User\Models\User;

use App\Enums\ChangeRequestStatus;
use App\Modules\Project\Models\Project;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string|null $cr_number
 * @property int $project_id
 * @property int $requested_by
 * @property string|null $title
 * @property string|null $description
 * @property string|null $impact_analysis
 * @property bool $is_billable
 * @property float|int|string|null $estimated_hours
 * @property float|int|string|null $estimated_cost
 * @property float|int|string|null $approved_cost
 * @property int|null $additional_days
 * @property ChangeRequestStatus $status
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property string|null $rejection_reason
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $requester
 * @property-read User|null $approver
 */
class ChangeRequest extends Model
{
    use HasCreatedUpdatedBy, HasFactory;

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
