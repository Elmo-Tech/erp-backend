<?php

namespace App\Modules\Template\Actions;

use App\Modules\Template\Models\ProposalTemplate;
use App\Modules\User\Models\User;

class CreateProposalTemplateAction
{
    public function execute(array $data, User $user): ProposalTemplate
    {
        return ProposalTemplate::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'content' => $data['content'],
            'placeholders' => $data['placeholders'] ?? [],
            'is_active' => $data['isActive'] ?? true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }
}
