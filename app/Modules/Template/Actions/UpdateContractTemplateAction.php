<?php

namespace App\Modules\Template\Actions;

use App\Modules\Contract\Models\ContractTemplate;
use App\Modules\User\Models\User;

class UpdateContractTemplateAction
{
    public function execute(ContractTemplate $template, array $data, User $user): ContractTemplate
    {
        $template->update([
            'name' => $data['name'] ?? $template->name,
            'type' => $data['type'] ?? $template->type,
            'content' => $data['content'] ?? $template->content,
            'placeholders' => $data['placeholders'] ?? $template->placeholders,
            'is_active' => $data['isActive'] ?? $template->is_active,
            'updated_by' => $user->id,
        ]);

        return $template->fresh();
    }
}
