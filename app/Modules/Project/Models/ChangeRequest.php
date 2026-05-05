<?php

namespace App\Modules\Project\Models;

use App\Enums\ChangeRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChangeRequest extends Model
{
    use SoftDeletes;

    protected $table = 'change_requests';

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'price',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ChangeRequestStatus::class,
            'price' => 'decimal:2',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The project this change request belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isDraft(): bool
    {
        return $this->status === ChangeRequestStatus::DRAFT;
    }

    public function isApproved(): bool
    {
        return $this->status === ChangeRequestStatus::APPROVED;
    }

    public function canBeSentToClient(): bool
    {
        return $this->status === ChangeRequestStatus::DRAFT;
    }
}
