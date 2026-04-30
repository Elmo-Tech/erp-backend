<?php

namespace App\Modules\SOP\Actions;

use App\Modules\SOP\Models\Directive;
use App\Modules\Settings\Models\User;

class GetTodayDirectiveForUserAction
{
    public function execute(User $user): ?Directive
    {
        $permissions = $user->getAllPermissions()->pluck('name')->all();

        return Directive::query()
            ->latest()
            ->get()
            ->first(function (Directive $directive) use ($permissions): bool {
                $requiredPermissions = $directive->target_permissions ?? [];

                if ($requiredPermissions === []) {
                    return true;
                }

                return count(array_intersect($requiredPermissions, $permissions)) === count($requiredPermissions);
            });
    }
}
