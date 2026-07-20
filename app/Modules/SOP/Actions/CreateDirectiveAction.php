<?php

namespace App\Modules\SOP\Actions;

use App\Modules\Settings\Models\User;
use App\Modules\SOP\Models\Directive;

class CreateDirectiveAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, User $creator): Directive
    {
        return Directive::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'phases' => $data['phases'],
            'target_permissions' => $data['target_permissions'] ?? [],
            'is_recurring' => $data['is_recurring'] ?? true,
            'created_by' => $creator->id,
        ]);
    }
}
