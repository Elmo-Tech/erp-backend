<?php

namespace App\Models;

use App\Enums\ProposalStatus;
use App\Traits\HasAuditTrail;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Proposal extends Model
{
    use HasFactory, HasCreatedUpdatedBy, HasAuditTrail;

    protected $fillable = [
        'proposal_number',
        'lead_id',
        'template_id',
        'title',
        'content',
        'file_path',
        'file_name',
        'status',
        'submission_date',
        'expiry_date',
        'sent_at',
        'viewed_at',
        'rejection_reason',
        'rejection_notes',
        'is_fast_track',
        'fast_track_reason',
        'internal_notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProposalStatus::class,
            'submission_date' => 'date',
            'expiry_date' => 'date',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'is_fast_track' => 'boolean',
        ];
    }

    /**
     * Get the lead that owns the proposal.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the template used for this proposal.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ProposalTemplate::class, 'template_id');
    }

    /**
     * Get the quotations linked to this proposal.
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    /**
     * Get the file URL.
     */
    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }

    /**
     * Check if proposal is expired.
     */
    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    /**
     * Check if proposal can be edited.
     */
    public function canBeEdited(): bool
    {
        return in_array($this->status, [ProposalStatus::DRAFT]);
    }

    /**
     * Scope for expiring proposals.
     */
    public function scopeExpiringIn($query, int $days)
    {
        return $query->where('status', ProposalStatus::SENT)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays($days)]);
    }

    /**
     * Scope for active proposals (not expired or rejected).
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [
            ProposalStatus::EXPIRED,
            ProposalStatus::REJECTED,
        ]);
    }

    /**
     * Generate unique proposal number.
     */
    public static function generateProposalNumber(): string
    {
        $year = date('Y');
        $lastProposal = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastProposal && $lastProposal->proposal_number
            ? (int) substr($lastProposal->proposal_number, -4) + 1
            : 1;

        return sprintf('PR-%s-%04d', $year, $sequence);
    }
}
