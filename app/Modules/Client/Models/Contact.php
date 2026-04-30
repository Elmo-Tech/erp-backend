<?php

namespace App\Modules\Client\Models;

use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    use HasCreatedUpdatedBy;

    protected $table = 'contacts';

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
}
