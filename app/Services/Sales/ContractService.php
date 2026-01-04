<?php

namespace App\Services\Sales;

use App\Enums\ContractStatus;
use App\Enums\MilestoneStatus;
use App\Enums\MilestoneTriggerType;
use App\Models\Contract;
use App\Models\ContractMilestone;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ContractService
{
    /**
     * Get paginated contracts with filters
     */
    public function getContracts(array $filters = []): LengthAwarePaginator
    {
        return QueryBuilder::for(Contract::class)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('project_id'),
                AllowedFilter::exact('quotation_id'),
                'search' => AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('contract_number', 'like', "%{$value}%")
                            ->orWhere('title', 'like', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts(['created_at', 'start_date', 'total_amount', 'signed_at'])
            ->allowedIncludes(['quotation', 'project', 'milestones', 'template'])
            ->defaultSort('-created_at')
            ->paginate($filters['perPage'] ?? 15);
    }

    /**
     * Create contract from quotation
     */
    public function createFromQuotation(Quotation $quotation, array $data, User $user): Contract
    {
        return DB::transaction(function () use ($quotation, $data, $user) {
            $contract = Contract::create([
                'contract_number' => Contract::generateContractNumber(),
                'quotation_id' => $quotation->id,
                'template_id' => $data['templateId'] ?? null,
                'title' => $data['title'],
                'content' => $data['content'],
                'party_one' => $data['partyOne'],
                'party_two' => $data['partyTwo'],
                'total_amount' => $quotation->final_price,
                'currency' => $quotation->currency,
                'payment_terms' => $data['paymentTerms'] ?? null,
                'start_date' => $data['startDate'],
                'end_date' => $data['endDate'] ?? null,
                'status' => ContractStatus::DRAFT,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Create milestones
            if (!empty($data['milestones'])) {
                $this->createMilestones($contract, $data['milestones']);
            }

            return $contract->load('milestones');
        });
    }

    /**
     * Create contract directly
     */
    public function createContract(array $data, User $user): Contract
    {
        return DB::transaction(function () use ($data, $user) {
            $contract = Contract::create([
                'contract_number' => Contract::generateContractNumber(),
                'quotation_id' => $data['quotationId'] ?? null,
                'project_id' => $data['projectId'] ?? null,
                'template_id' => $data['templateId'] ?? null,
                'title' => $data['title'],
                'content' => $data['content'],
                'party_one' => $data['partyOne'],
                'party_two' => $data['partyTwo'],
                'total_amount' => $data['totalAmount'],
                'currency' => $data['currency'] ?? 'SAR',
                'payment_terms' => $data['paymentTerms'] ?? null,
                'start_date' => $data['startDate'],
                'end_date' => $data['endDate'] ?? null,
                'status' => ContractStatus::DRAFT,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Create milestones
            if (!empty($data['milestones'])) {
                $this->createMilestones($contract, $data['milestones']);
            }

            return $contract->load('milestones');
        });
    }

    /**
     * Create milestones for contract
     */
    private function createMilestones(Contract $contract, array $milestones): void
    {
        foreach ($milestones as $index => $milestone) {
            $percentage = $milestone['percentage'] ?? 0;
            $amount = ($contract->total_amount * $percentage) / 100;

            ContractMilestone::create([
                'contract_id' => $contract->id,
                'title' => $milestone['title'],
                'description' => $milestone['description'] ?? null,
                'amount' => $amount,
                'percentage' => $percentage,
                'due_date' => $milestone['dueDate'] ?? null,
                'trigger_type' => $milestone['triggerType'] ?? MilestoneTriggerType::DATE->value,
                'trigger_value' => $milestone['triggerValue'] ?? null,
                'status' => MilestoneStatus::PENDING,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * Update contract
     */
    public function updateContract(Contract $contract, array $data, User $user): Contract
    {
        return DB::transaction(function () use ($contract, $data, $user) {
            $contract->update([
                'template_id' => $data['templateId'] ?? $contract->template_id,
                'title' => $data['title'] ?? $contract->title,
                'content' => $data['content'] ?? $contract->content,
                'party_one' => $data['partyOne'] ?? $contract->party_one,
                'party_two' => $data['partyTwo'] ?? $contract->party_two,
                'total_amount' => $data['totalAmount'] ?? $contract->total_amount,
                'currency' => $data['currency'] ?? $contract->currency,
                'payment_terms' => $data['paymentTerms'] ?? $contract->payment_terms,
                'start_date' => $data['startDate'] ?? $contract->start_date,
                'end_date' => $data['endDate'] ?? $contract->end_date,
                'updated_by' => $user->id,
            ]);

            // Update milestones if provided
            if (isset($data['milestones'])) {
                $contract->milestones()->delete();
                $this->createMilestones($contract, $data['milestones']);
            }

            return $contract->fresh(['milestones']);
        });
    }

    /**
     * Sign contract
     */
    public function signContract(Contract $contract, string $signedByClient, User $user): Contract
    {
        $contract->update([
            'status' => ContractStatus::SIGNED,
            'signed_at' => now(),
            'signed_by_client' => $signedByClient,
            'updated_by' => $user->id,
        ]);

        // Mark immediate milestones as due
        $contract->milestones()
            ->where('trigger_type', MilestoneTriggerType::IMMEDIATE)
            ->update(['status' => MilestoneStatus::DUE]);

        return $contract->fresh(['milestones']);
    }

    /**
     * Activate contract
     */
    public function activateContract(Contract $contract, User $user): Contract
    {
        $contract->update([
            'status' => ContractStatus::ACTIVE,
            'updated_by' => $user->id,
        ]);

        return $contract->fresh();
    }

    /**
     * Update milestone status based on project status
     */
    public function checkMilestoneTriggers(Contract $contract, string $projectStatus): void
    {
        $contract->milestones()
            ->where('trigger_type', MilestoneTriggerType::PROJECT_STATUS)
            ->where('trigger_value', $projectStatus)
            ->where('status', MilestoneStatus::PENDING)
            ->update(['status' => MilestoneStatus::DUE]);
    }

    /**
     * Mark milestone as paid
     */
    public function markMilestonePaid(ContractMilestone $milestone, ?int $invoiceId = null): ContractMilestone
    {
        $milestone->update([
            'status' => MilestoneStatus::PAID,
            'invoice_id' => $invoiceId,
            'paid_at' => now(),
        ]);

        return $milestone->fresh();
    }

    /**
     * Get milestones due for payment
     */
    public function getDueMilestones(): LengthAwarePaginator
    {
        return ContractMilestone::where('status', MilestoneStatus::DUE)
            ->with('contract')
            ->orderBy('due_date')
            ->paginate(10);
    }

    /**
     * Check and update overdue milestones
     */
    public function updateOverdueMilestones(): int
    {
        return ContractMilestone::whereIn('status', [MilestoneStatus::PENDING, MilestoneStatus::DUE])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->update(['status' => MilestoneStatus::OVERDUE]);
    }
}
