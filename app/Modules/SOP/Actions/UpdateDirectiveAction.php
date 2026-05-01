<?php

namespace App\Modules\SOP\Actions;

use App\Modules\SOP\Models\Directive;

class UpdateDirectiveAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Directive $directive, array $data): Directive
    {
        $directive->update($data);

        return $directive->refresh();
    }
}
