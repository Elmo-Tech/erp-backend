<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use SoftDeletes;

    protected $table = 'subscriptions';

    protected $fillable = [
        'name',
        'provider',
        'billing_cycle',
        'cost',
        'currency',
        'next_billing_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'cost'              => 'decimal:2',
            'next_billing_date' => 'date',
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
