<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceInvoiceItem extends Model
{
    protected $table = 'finance_invoice_items';

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantity'   => 'integer',
            'unit_price' => 'decimal:2',
            'total'      => 'decimal:2',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FinanceInvoice::class, 'invoice_id');
    }
}
