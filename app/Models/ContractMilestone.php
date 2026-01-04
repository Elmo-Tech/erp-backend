<?php

namespace App\Models;

use App\Enums\MilestoneStatus;
use App\Enums\MilestoneTriggerType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractMilestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'title',
        'description',
        'amount',
        'percentage',
        'due_date',
        'trigger_type',
        'trigger_value',
        'status',
        'invoice_id',
        'paid_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'percentage' => 'decimal:2',
            'due_date' => 'date',
            'trigger_type' => MilestoneTriggerType::class,
            'status' => MilestoneStatus::class,
            'paid_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Check if milestone is overdue
     */
    public function isOverdue(): bool
    {
        if ($this->status === MilestoneStatus::PAID) {
            return false;
        }

        return $this->due_date && $this->due_date->isPast();
    }
}
