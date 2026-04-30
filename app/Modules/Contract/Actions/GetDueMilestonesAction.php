<?php

namespace App\Modules\Contract\Actions;

use App\Enums\MilestoneStatus;
use App\Modules\Contract\Models\ContractMilestone;

class GetDueMilestonesAction
{
    public function execute()
    {
        return ContractMilestone::with(['contract.quotation.client', 'invoice'])
            ->where('status', MilestoneStatus::PENDING)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', now()->toDateString())
            ->orderBy('due_date')
            ->get();
    }
}
