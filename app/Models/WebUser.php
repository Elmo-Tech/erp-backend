<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Session/Web authentication identity. SELECT * is forbidden for the Web DB
 * principal; MFA factor state must only be accessed via MfaService.
 */
final class WebUser extends User
{
    protected $table = 'users';

    protected static function booted(): void
    {
        static::addGlobalScope('safe_web_projection', function (Builder $builder): void {
            $builder->select([
                'users.id', 'users.name', 'users.email', 'users.password', 'users.is_active',
            ]);
        });
    }
}
