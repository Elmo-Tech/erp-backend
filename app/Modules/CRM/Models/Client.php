<?php

namespace App\Modules\CRM\Models;

use App\Modules\User\Models\User;
use App\Traits\HasCreatedUpdatedBy;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasCreatedUpdatedBy, HasFactory, SoftDeletes;

    protected $table = 'clients';

    protected $fillable = [
        'type',
        'display_name',
        'legal_name',
        'company_name',
        'primary_email',
        'primary_phone',
        'secondary_phone',
        'position',
        'industry',
        'website',
        'tax_number',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'source',
        'lifecycle_stage',
        'status',
        'lead_temperature',
        'expected_budget',
        'expected_close_date',
        'became_client_at',
        'account_manager_id',
        'user_id',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'expected_budget' => 'decimal:2',
            'expected_close_date' => 'date',
            'became_client_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ClientFactory
    {
        return ClientFactory::new();
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeLeads($query)
    {
        return $query->where('lifecycle_stage', 'lead');
    }

    public function scopeActiveClients($query)
    {
        return $query->where('lifecycle_stage', 'client')
            ->where('status', 'active');
    }

    public function scopeCompanies($query)
    {
        return $query->where('type', 'company');
    }

    public function scopeIndividuals($query)
    {
        return $query->where('type', 'individual');
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn () => $this->display_name);
    }

    protected function email(): Attribute
    {
        return Attribute::get(fn () => $this->primary_email);
    }

    protected function phone(): Attribute
    {
        return Attribute::get(fn () => $this->primary_phone);
    }

    protected function company(): Attribute
    {
        return Attribute::get(fn () => $this->company_name);
    }
}
