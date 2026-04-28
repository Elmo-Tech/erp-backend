<?php

namespace App\Modules\Template\Actions;

use App\Modules\Proposal\Models\ProposalTemplate;
use App\Modules\Contract\Models\ContractTemplate;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Collection;

class GetTemplatesAction
{
    public function getProposalTemplates(?string $type = null): Collection
    {
        $query = ProposalTemplate::active();

        if ($type) {
            $query->where('type', $type);
        }

        return $query->orderBy('name')->get();
    }

    public function getContractTemplates(?string $type = null): Collection
    {
        $query = ContractTemplate::active();

        if ($type) {
            $query->where('type', $type);
        }

        return $query->orderBy('name')->get();
    }
}
