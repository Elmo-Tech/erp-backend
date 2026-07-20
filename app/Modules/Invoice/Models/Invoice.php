<?php

namespace App\Modules\Invoice\Models;

use App\Enums\InvoiceStatus;
use App\Modules\Client\Models\Client;
use App\Modules\Contract\Models\Contract;
use App\Modules\Project\Models\Project;
use App\Scopes\ClientScope;
use App\Traits\HasCreatedUpdatedBy;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $model_id
 * @property string $model_type
 * @property int|null $contract_id
 * @property string $invoice_number
 * @property float|int|string $amount
 * @property string $currency
 * @property InvoiceStatus $status
 * @property Carbon $due_date
 * @property Carbon|null $paid_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Project $project
 */
class Invoice extends Model
{
    use HasCreatedUpdatedBy, HasFactory, SoftDeletes;

    protected $fillable = [
        'model_id',
        'model_type',
        'project_id',
        'contract_id',
        'milestone_name',
        'invoice_number',
        'amount',
        'currency',
        'status',
        'due_date',
        'sent_at',
        'paid_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => InvoiceStatus::class,
            'due_date' => 'date',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Apply ClientScope when authenticated user is a client
        static::addGlobalScope(new ClientScope);
    }

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }

    public function setProjectIdAttribute($value): void
    {
        $this->attributes['model_type'] = Project::class;
        $this->attributes['model_id'] = $value;
    }

    public function getProjectIdAttribute(): ?int
    {
        return $this->model_type === Project::class ? (int) $this->model_id : null;
    }

    /**
     * Get the model that owns the invoice.
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the project that owns the invoice when this invoice is project-scoped.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'model_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'model_id');
    }

    /**
     * Get the contract that owns the invoice.
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Check if the invoice is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::OVERDUE ||
            ($this->status === InvoiceStatus::SENT && $this->due_date->isPast());
    }

    /**
     * Check if the invoice is payable.
     */
    public function isPayable(): bool
    {
        return $this->status->isPayable();
    }

    /**
     * Generate unique invoice number: INV-YYYY-NNNNNN
     */
    public static function generateInvoiceNumber(): string
    {
        $year = now()->year;
        $lastInvoice = static::withoutGlobalScopes()
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastInvoice
            ? (int) substr($lastInvoice->invoice_number, -6) + 1
            : 1;

        return sprintf('INV-%d-%06d', $year, $sequence);
    }
}
