<?php

namespace App\Modules\Contract\Models;

use App\Enums\MilestoneStatus;
use App\Enums\MilestoneTriggerType;
use App\Modules\Invoice\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $contract_id
 * @property string|null $title
 * @property string|null $description
 * @property float|int|string|null $amount
 * @property float|int|string|null $percentage
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property MilestoneTriggerType $trigger_type
 * @property string|null $trigger_value
 * @property MilestoneStatus $status
 * @property int|null $invoice_id
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property int|null $sort_order
 * @property-read Invoice|null $invoice
 */
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

    public function isOverdue(): bool
    {
        if ($this->status === MilestoneStatus::PAID) {
            return false;
        }

        return $this->due_date && $this->due_date->isPast();
    }
}
