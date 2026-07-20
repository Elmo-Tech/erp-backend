<?php

namespace App\Modules\Finance\Models;

use App\Modules\Client\Models\Client;
use App\Modules\Project\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceInvoice extends Model
{
    use SoftDeletes;

    protected $table = 'finance_invoices';

    protected $fillable = [
        'client_id',
        'project_id',
        'invoice_number',
        'invoice_type',
        'billing_cycle',
        'subscription_id',
        'issue_date',
        'due_date',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FinanceInvoiceItem::class, 'invoice_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Generate a unique invoice number: FINV-YYYY-NNNNNN
     */
    public static function generateInvoiceNumber(): string
    {
        $year = now()->year;

        $last = static::withTrashed()
            ->whereYear('created_at', $year)
            ->orderByDesc('id')
            ->first();

        $sequence = $last
            ? (int) substr($last->invoice_number, -6) + 1
            : 1;

        return sprintf('FINV-%d-%06d', $year, $sequence);
    }

    public function isOverdue(): bool
    {
        return $this->status === 'sent' && $this->due_date->isPast();
    }
}
