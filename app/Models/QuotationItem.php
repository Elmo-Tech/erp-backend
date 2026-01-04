<?php

namespace App\Models;

use App\Enums\ResourceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_id',
        'resource_type',
        'description',
        'estimated_hours',
        'hourly_rate',
        'internal_hourly_cost',
        'subtotal',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'resource_type' => ResourceType::class,
            'estimated_hours' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'internal_hourly_cost' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
}
