<?php

namespace App\Modules\Contract\Actions;

use App\Enums\MilestoneStatus;
use App\Modules\Contract\Models\ContractMilestone;

class MarkMilestonePaidAction
{
    public function execute(ContractMilestone $milestone, ?int $invoiceId = null): ContractMilestone
    {
        $milestone->update([
            'status' => MilestoneStatus::PAID,
            'invoice_id' => $invoiceId ?? $milestone->invoice_id,
            'paid_at' => now(),
        ]);

        return $milestone->fresh(['contract.quotation.lead', 'invoice']);
    }
}
