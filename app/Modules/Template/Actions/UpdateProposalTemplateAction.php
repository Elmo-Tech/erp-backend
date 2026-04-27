<?php

namespace App\Modules\Template\Actions;

use App\Modules\Template\Models\ProposalTemplate;
use App\Modules\User\Models\User;

class UpdateProposalTemplateAction
{
    public function execute(ProposalTemplate $template, array $data, User $user): ProposalTemplate
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
