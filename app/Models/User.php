<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'internal_hourly_cost',
        'skills',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'internal_hourly_cost',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'internal_hourly_cost' => 'decimal:2',
            'skills' => 'array',
        ];
    }

    /**
     * Make internal_hourly_cost visible for serialization.
     * This should only be called when the requesting user is a Super Admin.
     *
     * @return $this
     */
    public function makeInternalCostVisible(): self
    {
        return $this->makeVisible('internal_hourly_cost');
    }

    /**
     * Convert the model to an array with internal cost visible.
     * This should only be used when the requesting user is a Super Admin.
     *
     * @return array<string, mixed>
     */
    public function toArrayWithInternalCost(): array
    {
        return $this->makeVisible('internal_hourly_cost')->toArray();
    }
}
