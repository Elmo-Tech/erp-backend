<?php

namespace App\Modules\Lead\Models;

use App\Enums\ContractModel;
use App\Enums\ContractStatus;
use App\Enums\DocumentCreationType;
use App\Modules\Client\Models\Client;
use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $lead_id
 * @property int|null $client_id
 * @property int|null $project_id
 * @property ContractStatus $status
 * @property DocumentCreationType $creation_type
 * @property string|null $skip_reason
 * @property int|null $assigned_to
 * @property string|null $file_path
 * @property ContractModel $contract_model
 * @property int|null $development_days
 * @property int|null $review_days
 * @property float|null $delay_penalty
 * @property bool $source_code_handover
 * @property float|null $setup_fee
 * @property float|null $recurring_fee
 * @property string|null $billing_cycle
 * @property array|null $payment_stages
 */
class Contract extends Model
{
    use HasCreatedUpdatedBy, SoftDeletes;

    protected $table = 'contracts';

    protected $fillable = [
        'lead_id',
        'client_id',
        'project_id',
        'status',
        'creation_type',
        'skip_reason',
        'assigned_to',
        'file_path',
        'contract_model',
        'development_days',
        'review_days',
        'testing_days',
        'delay_penalty',
        'source_code_handover',
        'execution_start_date',
        'working_days_per_week',
        'currency',
        'total_amount',
        'setup_fee',
        'recurring_fee',
        'billing_cycle',
        'subscription_start_date',
        'apply_promo',
        'promo_discount',
        'addons',
        'payment_stages',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContractStatus::class,
            'creation_type' => DocumentCreationType::class,
            'contract_model' => ContractModel::class,
            'delay_penalty' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'setup_fee' => 'decimal:2',
            'recurring_fee' => 'decimal:2',
            'promo_discount' => 'decimal:2',
            'source_code_handover' => 'boolean',
            'apply_promo' => 'boolean',
            'execution_start_date' => 'date',
            'subscription_start_date' => 'date',
            'payment_stages' => 'array',
            'addons' => 'array',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
