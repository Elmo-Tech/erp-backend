<?php

namespace App\Domain\Companies;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Company membership and global role grants are separate authorization boundaries.
 * No actual ledgers, payroll or consolidated values are returned by this module.
 */
final class CompanyAuthorization
{
    private const ROLES = [
        'company_manager' => ['company.view', 'projects.view', 'finance.view'],
        'project_manager' => ['company.view', 'projects.view'],
        'project_member' => ['company.view', 'projects.view'],
        'finance' => ['company.view', 'finance.view', 'finance.export'],
        'hr' => ['company.view', 'payroll.view'],
        'external_accountant' => ['company.view', 'finance.view', 'finance.export'],
        'viewer' => ['company.view'],
    ];

    public function isCentralManager(User $user): bool
    {
        return $user->is_active && DB::table('global_role_assignments')
            ->where('user_id', $user->id)
            ->where('role_key', 'central_management')
            ->where('status', 'active')
            ->exists();
    }

    public function canViewConsolidation(User $user): bool
    {
        return $this->isCentralManager($user) && DB::table('global_role_assignments')
            ->where('user_id', $user->id)->where('role_key', 'consolidation_viewer')
            ->where('status', 'active')->exists();
    }

    public function accessibleCompanyIds(User $user): array
    {
        if (! $user->is_active) {
            return [];
        }
        if ($this->isCentralManager($user)) {
            return DB::table('companies')->where('status', 'active')->pluck('id')->all();
        }
        return DB::table('company_memberships as m')
            ->join('companies as c', 'c.id', '=', 'm.company_id')
            ->where('m.user_id', $user->id)
            ->where('m.status', 'active')->where('c.status', 'active')
            ->pluck('c.id')->all();
    }

    public function canSelect(User $user, string $companyId): bool
    {
        return in_array($companyId, $this->accessibleCompanyIds($user), true);
    }

    public function permissions(User $user, string $companyId): array
    {
        if (! $this->canSelect($user, $companyId)) {
            return [];
        }
        $roles = DB::table('company_role_assignments as r')
            ->join('company_memberships as m', function ($join): void {
                $join->on('m.id', '=', 'r.membership_id')->on('m.company_id', '=', 'r.company_id');
            })
            ->where('m.user_id', $user->id)->where('m.status', 'active')
            ->where('r.company_id', $companyId)->pluck('r.role_key');

        $permissions = [];
        foreach ($roles as $role) {
            foreach (self::ROLES[$role] ?? [] as $permission) {
                $permissions[$permission] = true;
            }
        }
        if ($this->isCentralManager($user)) {
            $permissions['company.view'] = true;
        }
        return array_keys($permissions);
    }

    public function has(User $user, string $companyId, string $permission): bool
    {
        return in_array($permission, $this->permissions($user, $companyId), true);
    }

    public function companyList(User $user): array
    {
        $ids = $this->accessibleCompanyIds($user);
        if ($ids === []) {
            return [];
        }
        return DB::table('companies')->whereIn('id', $ids)->orderBy('code')
            ->get(['id', 'code', 'legal_name', 'country_code'])
            ->map(fn ($company): array => [
                'id' => $company->id,
                'code' => $company->code,
                'legal_name' => $company->legal_name,
                'country_code' => $company->country_code,
                'permissions' => $this->permissions($user, $company->id),
            ])->all();
    }
}
