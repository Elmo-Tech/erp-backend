<?php

namespace App\Modules\Contract\Actions;

use App\Enums\ContractStatus;
use App\Enums\MilestoneStatus;
use App\Modules\Contract\Models\Contract;
use App\Modules\Contract\Models\ContractMilestone;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateContractAction
{
    public function execute(Contract $contract, array $data, ?User $user = null): Contract
    {
        return DB::transaction(function () use ($contract, $data, $user) {
            $actorId = $user?->id ?? auth()->id();

            $startDate = $data['startDate'] ?? $contract->start_date;
            $expiryDate = array_key_exists('expiryDate', $data)
                ? $data['expiryDate']
                : (array_key_exists('expiryDays', $data)
                    ? Contract::calculateExpiryDate($startDate, (int) $data['expiryDays'])->toDateString()
                    : optional($contract->expiry_date)?->toDateString());

            $contract->update([
                'file_number' => $data['fileNumber'] ?? $contract->file_number,
                'template_id' => $data['templateId'] ?? $contract->template_id,
                'task_id' => $data['taskId'] ?? $contract->task_id,
                'project_id' => $data['projectId'] ?? $contract->project_id,
                'title' => $data['title'] ?? $contract->title,
                'content' => $data['content'] ?? $contract->content,
                'deliverables' => array_key_exists('deliverables', $data) ? $data['deliverables'] : $contract->deliverables,
                'party_one' => $data['partyOne'] ?? $contract->party_one,
                'party_two' => $data['partyTwo'] ?? $contract->party_two,
                'total_amount' => $data['totalAmount'] ?? $contract->total_amount,
                'currency' => $data['currency'] ?? $contract->currency,
                'payment_terms' => $data['paymentTerms'] ?? $contract->payment_terms,
                'start_date' => $startDate,
                'end_date' => $data['endDate'] ?? $contract->end_date,
                'expiry_date' => $expiryDate,
                'preparation_deadline' => $data['preparationDeadline'] ?? $contract->preparation_deadline,
                'project_duration' => $data['projectDuration'] ?? $contract->project_duration,
                'updated_by' => $actorId,
            ]);

            if (array_key_exists('milestones', $data)) {
                $contract->milestones()->delete();

                if (! empty($data['milestones'])) {
                    $this->createMilestones($contract, $data['milestones']);
                }
            }

            return $contract->fresh()->load(['milestones.invoice', 'quotation.lead', 'project', 'template']);
        });
    }

    private function createMilestones(Contract $contract, array $milestones): void
    {
        $sortOrder = 0;

        foreach ($milestones as $milestone) {
            $percentage = $milestone['percentage'] ?? 0;
            $amount = ($contract->total_amount * $percentage) / 100;

            ContractMilestone::create([
                'contract_id' => $contract->id,
                'title' => $milestone['title'],
                'description' => $milestone['description'] ?? null,
                'amount' => $amount,
                'percentage' => $percentage,
                'due_date' => $milestone['dueDate'] ?? null,
                'trigger_type' => $milestone['triggerType'] ?? \App\Enums\MilestoneTriggerType::DATE,
                'trigger_value' => $milestone['triggerValue'] ?? null,
                'status' => MilestoneStatus::PENDING,
                'sort_order' => $sortOrder++,
            ]);
        }
    }
}
