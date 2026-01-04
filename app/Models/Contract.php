<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use HasFactory, HasCreatedUpdatedBy;

    protected $fillable = [
        'contract_number',
        'quotation_id',
        'project_id',
        'template_id',
        'title',
        'content',
        'party_one',
        'party_two',
        'total_amount',
        'currency',
        'payment_terms',
        'start_date',
        'end_date',
        'signed_at',
        'signed_by_client',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'party_one' => 'array',
            'party_two' => 'array',
            'total_amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'signed_at' => 'datetime',
            'status' => ContractStatus::class,
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'template_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ContractMilestone::class)->orderBy('sort_order');
    }

    public function handover(): HasMany
    {
        return $this->hasMany(ProjectHandover::class);
    }

    /**
     * Generate unique contract number
     */
    public static function generateContractNumber(): string
    {
        $year = date('Y');
        $lastContract = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastContract ? (int) substr($lastContract->contract_number, -4) + 1 : 1;

        return sprintf('CT-%s-%04d', $year, $sequence);
    }
}
