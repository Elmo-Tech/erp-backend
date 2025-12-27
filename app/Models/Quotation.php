<?php

namespace App\Models;

use App\Contracts\Trackable;
use App\Enums\QuotationStatus;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Quotation extends Model implements Trackable
{
    use HasFactory, HasCreatedUpdatedBy;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'lead_id',
        'estimations',
        'hourly_rate',
        'discount_percentage',
        'final_price',
        'currency',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estimations' => 'array',
            'hourly_rate' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
            'final_price' => 'decimal:2',
            'status' => QuotationStatus::class,
        ];
    }

    /**
     * Get the lead that this quotation belongs to.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get all time entries for this trackable entity.
     */
    public function timeEntries(): MorphMany
    {
        return $this->morphMany(TimeEntry::class, 'trackable');
    }

    /**
     * Get the display name for this trackable entity.
     */
    public function getTrackableDisplayName(): string
    {
        return 'Quotation #' . $this->id . ' - ' . $this->lead->name;
    }
}