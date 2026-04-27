<?php

namespace App\Modules\Contract\Models;

use App\Enums\ContractStatus;
use App\Modules\Client\Models\Client;
use App\Modules\Project\Models\Project;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $contract_number
 * @property int|null $quotation_id
 * @property int|null $project_id
 * @property int|null $template_id
 * @property string|null $title
 * @property string|null $content
 * @property array<string, mixed>|null $party_one
 * @property array<string, mixed>|null $party_two
 * @property float|int|string|null $total_amount
 * @property string|null $currency
 * @property string|null $payment_terms
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property \Illuminate\Support\Carbon|null $expiry_date
 * @property \Illuminate\Support\Carbon|null $signed_at
 * @property string|null $signed_by_client
 * @property ContractStatus $status
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read Quotation $quotation
 * @property-read Project|null $project
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContractMilestone> $milestones
 */
class Contract extends Model
{
    use HasCreatedUpdatedBy, HasFactory;

    protected $fillable = [
        'contract_number',
        'file_number',
        'quotation_id',
        'project_id',
        'template_id',
        'task_id',
        'client_id',
        'title',
        'content',
        'deliverables',
        'party_one',
        'party_two',
        'total_amount',
        'currency',
        'payment_terms',
        'start_date',
        'end_date',
        'expiry_date',
        'preparation_deadline',
        'project_duration',
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
            'deliverables' => 'array',
            'total_amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'expiry_date' => 'date',
            'preparation_deadline' => 'date',
            'signed_at' => 'datetime',
            'status' => ContractStatus::class,
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Quotation::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'template_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Task\Models\Task::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ContractMilestone::class)->orderBy('sort_order');
    }

    public function isSigned(): bool
    {
        return $this->status === ContractStatus::SIGNED;
    }

    public function canBeSigned(): bool
    {
        return in_array($this->status, [ContractStatus::DRAFT, ContractStatus::PENDING_SIGNATURE]);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public static function generateContractNumber(?string $fileNumber = null): string
    {
        $year = date('Y');

        if ($fileNumber) {
            return sprintf('CV-%s-%s', $year, $fileNumber);
        }

        $lastContract = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastContract && $lastContract->contract_number
            ? (int) substr($lastContract->contract_number, -4) + 1
            : 1;

        return sprintf('CV-%s-%04d', $year, $sequence);
    }

    public static function calculateExpiryDate($startDate, int $expiryDays): \Carbon\Carbon
    {
        $start = \Carbon\Carbon::parse($startDate);

        return $start->addDays($expiryDays);
    }
}
