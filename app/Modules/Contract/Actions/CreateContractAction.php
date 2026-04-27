<?php

namespace App\Modules\Contract\Actions;

use App\Enums\ContractStatus;
use App\Enums\MilestoneStatus;
use App\Enums\MilestoneTriggerType;
use App\Enums\QuotationStatus;
use App\Modules\Contract\Models\Contract;
use App\Modules\Contract\Models\ContractMilestone;
use App\Modules\Project\Enums\ProjectStatus;
use App\Modules\Project\Models\Project;
use App\Modules\User\Models\User;
use App\Models\Quotation;
use App\Modules\Project\Actions\ConvertQuotationToProjectAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateContractAction
{
    public function __construct(
        private ConvertQuotationToProjectAction $projectConversionService
    ) {}

    public function execute(array $data, ?User $user = null): Contract
    {
        return DB::transaction(function () use ($data, $user) {
            $dataSource = $data['dataSource'] ?? 'new_terms';
            $startDate = \Carbon\Carbon::parse($data['startDate']);
            $expiryDays = $data['expiryDays'];
            $actorId = $user?->id ?? auth()->id();

            $expiryDate = now()->addDays($expiryDays);
            $fileNumber = $data['fileNumber'] ?? null;
            $contractNumber = Contract::generateContractNumber($fileNumber);

            $contractData = [
                'contract_number' => $contractNumber,
                'file_number' => $fileNumber,
                'title' => $data['title'],
                'content' => $data['content'] ?? '',
                'deliverables' => $data['deliverables'] ?? [],
                'project_duration' => $data['projectDuration'] ?? null,
                'start_date' => $startDate->toDateString(),
                'end_date' => $data['endDate'] ?? null,
                'expiry_date' => $expiryDate->toDateString(),
                'preparation_deadline' => $data['preparationDeadline'] ?? null,
                'party_one' => $data['partyOne'] ?? [],
                'party_two' => $data['partyTwo'] ?? [],
                'payment_terms' => $data['paymentTerms'] ?? null,
                'currency' => $data['currency'] ?? 'SAR',
                'status' => ContractStatus::DRAFT,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ];

            if ($dataSource === 'from_quotation') {
                $contractData = $this->handleFromQuotation($contractData, $data);
            } else {
                $contractData = $this->handleNewTerms($contractData, $data);
            }

            $shouldCopyMilestones = $dataSource === 'from_quotation' && ! empty($contractData['_copy_milestones']);

            unset($contractData['_copy_milestones'], $contractData['_quotation_milestones']);

            $contract = Contract::create($contractData);

            if ($shouldCopyMilestones) {
                $quotation = Quotation::with('milestones')->find($data['quotationId']);
                if ($quotation && $quotation->milestones->isNotEmpty()) {
                    $this->copyMilestonesFromQuotation($contract, $quotation);
                }
            }

            if ($dataSource === 'new_terms' && ! empty($data['milestones'])) {
                $this->createMilestones($contract, $data['milestones']);
            }

            return $contract->fresh()->load(['milestones.invoice', 'quotation.lead', 'project', 'template']);
        });
    }

    public function createFromQuotation(Quotation $quotation, array $data = [], ?User $user = null): Contract
    {
        $payload = $data;
        $payload['dataSource'] = 'from_quotation';
        $payload['quotationId'] = $quotation->id;

        return $this->execute($payload, $user);
    }

    private function handleFromQuotation(array $contractData, array $data): array
    {
        $quotationId = $data['quotationId'] ?? null;

        if (! $quotationId) {
            throw new \InvalidArgumentException('Quotation ID is required for from_quotation data source');
        }

        $quotation = Quotation::with(['lead', 'milestones'])->findOrFail($quotationId);

        if ($quotation->status !== QuotationStatus::APPROVED) {
            throw new \InvalidArgumentException('Quotation must be approved to create a contract');
        }

        if (Contract::where('quotation_id', $quotationId)->exists()) {
            throw new \InvalidArgumentException('A contract already exists for this quotation');
        }

        $contractData['quotation_id'] = $quotationId;
        $contractData['task_id'] = $data['taskId'] ?? $quotation->task_id;
        $contractData['project_id'] = $data['projectId'] ?? null;
        $contractData['template_id'] = $data['templateId'] ?? $quotation->template_id;
        $contractData['total_amount'] = $quotation->final_price;
        $contractData['currency'] = $quotation->currency;

        if (empty($contractData['party_two'])) {
            $contractData['party_two'] = [
                'name' => $quotation->lead->name,
                'representative' => $quotation->lead->name,
                'email' => $quotation->lead->email,
                'company' => $quotation->lead->company,
                'phone' => $quotation->lead->phone ?? '',
                'title' => '',
                'address' => '',
            ];
        }

        if (empty($contractData['deliverables']) && $quotation->scope_description) {
            $contractData['deliverables'] = $this->extractDeliverablesFromScope($quotation->scope_description);
        }

        $contractData['_copy_milestones'] = true;

        return $contractData;
    }

    private function handleNewTerms(array $contractData, array $data): array
    {
        $contractData['total_amount'] = $data['totalAmount'];
        $contractData['currency'] = $data['currency'] ?? 'SAR';
        $contractData['quotation_id'] = $data['quotationId'] ?? null;
        $contractData['task_id'] = $data['taskId'] ?? null;
        $contractData['project_id'] = $data['projectId'] ?? null;
        $contractData['template_id'] = $data['templateId'] ?? null;

        return $contractData;
    }

    private function extractDeliverablesFromScope(string $scopeDescription): array
    {
        $lines = preg_split('/[\n\r•\-]+/', $scopeDescription);
        $deliverables = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (strlen($line) > 3 && strlen($line) < 500) {
                $deliverables[] = $line;
            }
        }

        return $deliverables;
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
                'trigger_type' => $milestone['triggerType'] ?? MilestoneTriggerType::DATE,
                'trigger_value' => $milestone['triggerValue'] ?? null,
                'status' => MilestoneStatus::PENDING,
                'sort_order' => $sortOrder++,
            ]);
        }
    }

    private function copyMilestonesFromQuotation(Contract $contract, Quotation $quotation): void
    {
        $milestones = $quotation->milestones;
        $sortOrder = 0;

        foreach ($milestones as $quotationMilestone) {
            $percentage = $quotationMilestone->percentage;
            $amount = ($contract->total_amount * $percentage) / 100;
            $dueDate = $quotationMilestone->due_date
                ? \Carbon\Carbon::parse($quotationMilestone->due_date)
                : null;

            ContractMilestone::create([
                'contract_id' => $contract->id,
                'title' => $quotationMilestone->name,
                'description' => $quotationMilestone->description,
                'amount' => $amount,
                'percentage' => $percentage,
                'due_date' => $dueDate?->toDateString(),
                'trigger_type' => MilestoneTriggerType::DATE,
                'trigger_value' => null,
                'status' => MilestoneStatus::PENDING,
                'sort_order' => $sortOrder++,
            ]);
        }
    }
}
