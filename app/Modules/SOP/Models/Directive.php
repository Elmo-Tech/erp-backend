<?php

namespace App\Modules\SOP\Models;

use App\Modules\Settings\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Directive extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'phases',
        'target_permissions',
        'is_recurring',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'phases' => 'array',
            'target_permissions' => 'array',
            'is_recurring' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dailyCheckins(): HasMany
    {
        return $this->hasMany(DailyCheckin::class);
    }
}
