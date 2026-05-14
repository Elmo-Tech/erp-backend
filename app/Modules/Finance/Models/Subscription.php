<?php

namespace App\Modules\Finance\Models;

use App\Modules\Client\Models\Client;
use App\Modules\Project\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use SoftDeletes;

    protected $table = 'subscriptions';

    protected $fillable = [
        'client_id',
        'project_id',
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
            'cost' => 'decimal:2',
            'next_billing_date' => 'date',
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
