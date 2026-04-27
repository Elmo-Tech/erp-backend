<?php

namespace App\Modules\Template\Actions;

use App\Modules\Contract\Models\ContractTemplate;
use App\Modules\User\Models\User;

class CreateContractTemplateAction
{
    public function execute(array $data, User $user): ContractTemplate
    {
        return ContractTemplate::create([
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
