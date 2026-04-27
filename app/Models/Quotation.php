<?php

namespace App\Models;

use App\Modules\User\Models\User;

use App\Contracts\Trackable;
use App\Enums\CommissionType;
use App\Enums\QuotationStatus;
use App\Enums\RejectionReason;
use App\Modules\TimeEntry\Models\TimeEntry;
use App\Traits\HasAuditTrail;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string|null $quotation_number
 * @property int $lead_id
 * @property float|int|string|null $final_price
 * @property string|null $currency
 * @property QuotationStatus $status
 * @property \Illuminate\Support\Carbon|null $expiry_date
 * @property-read Lead $lead
 */
class Quotation extends Model implements Trackable
{
    use HasAuditTrail, HasCreatedUpdatedBy, HasFactory, SoftDeletes;

    protected $fillable = [
        'quotation_number',
        'file_number',
        'lead_id',
        'proposal_id',
        'template_id',
        'scope_description',
        'estimations',
        'hourly_rate',
        'discount_percentage',
        'final_price',
        'currency',
        'status',
        'expiry_date',
        'sent_at',
        'viewed_at',
        'rejection_reason',
        'rejection_notes',
        'needs_revision',
        'revision_notes',
        'task_id',
        'commission_type',
        'commission_percentage',
        'commission_amount',
        'sales_commission_percentage',
        'sales_commission_amount',
        'commission_user_id',
        'internal_cost',
        'profit_margin',
        'override_price',
        'override_reason',
        'notes',
        'terms_conditions',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'estimations' => 'array',
            'hourly_rate' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
            'final_price' => 'decimal:2',
            'status' => QuotationStatus::class,
            'expiry_date' => 'date',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'rejection_reason' => RejectionReason::class,
            'commission_type' => CommissionType::class,
            'commission_percentage' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'internal_cost' => 'decimal:2',
            'profit_margin' => 'decimal:2',
            'override_price' => 'decimal:2',
            'needs_revision' => 'boolean',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProposalTemplate::class, 'template_id');
    }

    public function commissionUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commission_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(QuotationMilestone::class)->orderBy('display_order');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }

    public function timeEntries(): MorphMany
    {
        return $this->morphMany(TimeEntry::class, 'trackable');
    }

    public function getTrackableDisplayName(): string
    {
        return ($this->quotation_number ?? 'Quotation #'.$this->id).' - '.$this->lead->name;
    }

    /**
     * Check if quotation is expired
     */
    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    /**
     * Scope for expiring quotations
     */
    public function scopeExpiringIn($query, int $days)
    {
        return $query->where('status', QuotationStatus::SENT)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays($days)]);
    }

    /**
     * Generate unique quotation number
     * Format: QV-YEAR-FILENUMBER
     */
    public static function generateQuotationNumber(?string $fileNumber = null): string
    {
        $year = date('Y');

        // If file number is provided, use it in the format
        if ($fileNumber) {
            return sprintf('QV-%s-%s', $year, $fileNumber);
        }

        // Otherwise generate sequence number
        $lastQuotation = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastQuotation && $lastQuotation->quotation_number
            ? (int) substr($lastQuotation->quotation_number, -4) + 1
            : 1;

        return sprintf('QV-%s-%04d', $year, $sequence);
    }
}
