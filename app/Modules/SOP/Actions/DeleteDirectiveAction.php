<?php

namespace App\Modules\SOP\Actions;

use App\Modules\SOP\Models\Directive;

class DeleteDirectiveAction
{
    public function execute(Directive $directive): void
    {
        $directive->delete();
    }
}
