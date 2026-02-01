<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Contract;
use App\Scopes\ClientScope;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory, HasCreatedUpdatedBy;

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
        static::addGlobalScope(new ClientScope());
    }

    /**
     * Get the project that owns the invoice.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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