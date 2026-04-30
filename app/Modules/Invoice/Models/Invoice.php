<?php

namespace App\Modules\Invoice\Models;

use App\Enums\InvoiceStatus;
use App\Modules\Contract\Models\Contract;
use App\Modules\CRM\Models\Client;
use App\Modules\Project\Models\Project;
use App\Scopes\ClientScope;
use App\Traits\HasCreatedUpdatedBy;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $contract_id
 * @property string $invoice_number
 * @property float|int|string $amount
 * @property string $currency
 * @property InvoiceStatus $status
 * @property \Illuminate\Support\Carbon $due_date
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Project $project
 */
class Invoice extends Model
{
    use HasCreatedUpdatedBy, HasFactory, SoftDeletes;

    protected $fillable = [
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

    /**
     * Get the project that owns the invoice.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function client(): HasOneThrough
    {
        return $this->hasOneThrough(
            Client::class,
            Project::class,
            'id',
            'id',
            'project_id',
            'client_id'
        );
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
