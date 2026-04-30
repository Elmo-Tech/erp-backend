<?php

namespace App\Modules\SOP\Actions;

use App\Modules\SOP\Models\DailyCheckin;
use App\Modules\SOP\Models\Directive;
use App\Modules\Settings\Models\User;

class SubmitDailyCheckinAction
{
    /**
     * @param  array<int, string>  $focusItems
     */
    public function execute(User $user, Directive $directive, array $focusItems): DailyCheckin
    {
        return DailyCheckin::create([
            'user_id' => $user->id,
            'directive_id' => $directive->id,
            'focus_items' => array_values($focusItems),
            'submitted_at' => now(),
        ]);
    }
}
