<?php

namespace App\Modules\HR\Models;

use App\Modules\Settings\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int    $id
 * @property int    $user_id
 * @property float  $amount
 * @property string $type   manual_bonus|manual_deduction
 * @property string $reason
 * @property \Illuminate\Support\Carbon $transaction_date
 * @property-read User $user
 */
class PayrollTransaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'amount',
        'type',
        'reason',
        'transaction_date',
    ];

    protected function casts(): array
    {
        return [
            'amount'           => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // -------------------------------------------------------------------------
    // Domain helpers
    // -------------------------------------------------------------------------

    public function isBonus(): bool
    {
        return $this->type === 'manual_bonus';
    }

    public function isDeduction(): bool
    {
        return $this->type === 'manual_deduction';
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeBonuses($query)
    {
        return $query->where('type', 'manual_bonus');
    }

    public function scopeDeductions($query)
    {
        return $query->where('type', 'manual_deduction');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
