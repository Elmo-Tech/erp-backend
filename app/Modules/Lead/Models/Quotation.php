<?php

namespace App\Modules\Lead\Models;

use App\Enums\DocumentCreationType;
use App\Enums\QuotationStatus;
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
 * @property QuotationStatus $status
 * @property DocumentCreationType $creation_type
 * @property string|null $skip_reason
 * @property int|null $assigned_to
 * @property string|null $file_path
 * @property string $currency
 * @property float|null $base_price
 * @property string|null $commission_type
 * @property float|null $final_total
 * @property array|null $payment_stages
 */
class Quotation extends Model
{
    use HasCreatedUpdatedBy, SoftDeletes;

    protected $table = 'quotations';

    protected $fillable = [
        'lead_id',
        'client_id',
        'project_id',
        'status',
        'creation_type',
        'skip_reason',
        'assigned_to',
        'file_path',
        'currency',
        'base_price',
        'commission_type',
        'final_total',
        'payment_stages',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'creation_type' => DocumentCreationType::class,
            'base_price' => 'decimal:2',
            'final_total' => 'decimal:2',
            'payment_stages' => 'array',
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
