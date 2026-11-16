<?php

namespace App\Domain\Companies;

use Illuminate\Support\Facades\DB;

final class CompanyAccess
{
    /**
     * Deny by default. Never derive legal/financial visibility from project membership.
     */
    public function userCanAccess(int $userId, string $companyId): bool
    {
        return DB::table('company_memberships')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }
}

