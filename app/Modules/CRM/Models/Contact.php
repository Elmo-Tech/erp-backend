<?php

namespace App\Modules\CRM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'first_name',
        'last_name',
        'display_name',
        'email',
        'phone',
        'mobile',
        'position',
        'department',
        'is_primary',
        'contact_preferences',
        'social_links',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'contact_preferences' => 'array',
            'social_links' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }
}
