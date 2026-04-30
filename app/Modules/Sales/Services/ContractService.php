<?php

namespace App\Modules\Sales\Services;

use App\Enums\ContractStatus;
use App\Enums\MilestoneStatus;
use App\Enums\MilestoneTriggerType;
use App\Modules\Project\Enums\ProjectStatus;
use App\Enums\QuotationStatus;
use App\Modules\Contract\Models\Contract;
use App\Modules\Contract\Models\ContractMilestone;
use App\Modules\Project\Models\Project;
use App\Modules\Quotation\Models\Quotation;
use App\Modules\User\Models\User;
use App\Modules\Project\Actions\ConvertQuotationToProjectAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ContractService
{
    public function __construct(
        private ConvertQuotationToProjectAction $projectConversionService
    ) {}

    /**
     * Get paginated contracts with filters
     */
    public function getContracts(array $filters = []): LengthAwarePaginator
    {
        return QueryBuilder::for(Contract::class)
            ->with(['quotation.client', 'project', 'template', 'milestones.invoice'])
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::callback('projectId', fn ($query, $value) => $query->where('project_id', $value)),
                AllowedFilter::callback('quotationId', fn ($query, $value) => $query->where('quotation_id', $value)),
                AllowedFilter::callback('clientId', function ($query, $value) {
                    $query->whereHas('quotation', fn ($quotationQuery) => $quotationQuery->where('client_id', $value));
                }),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($contractQuery) use ($value) {
                        $contractQuery->where('title', 'like', "%{$value}%")
                            ->orWhere('contract_number', 'like', "%{$value}%")
                            ->orWhere('file_number', 'like', "%{$value}%")
                            ->orWhereHas('quotation', function ($quotationQuery) use ($value) {
                                $quotationQuery->where('quotation_number', 'like', "%{$value}%")
                                    ->orWhereHas('client', function ($clientQuery) use ($value) {
                                        $clientQuery->where('display_name', 'like', "%{$value}%")
                                            ->orWhere('company_name', 'like', "%{$value}%");
                                    });
                            });
                    });
                }),
            ])
            ->allowedSorts(['created_at', 'signed_at', 'status', 'start_date', 'end_date'])
            ->defaultSort('-created_at')
            ->paginate($filters['perPage'] ?? 15);
    }

    /**
     * Create a new contract with proper handling based on data source
     */
    public function createContract(array $data, ?User $user = null): Contract
    {
        return DB::transaction(function () use ($data, $user) {
            $dataSource = $data['dataSource'] ?? 'new_terms';
            $startDate = \Carbon\Carbon::parse($data['startDate']);
            $expiryDays = $data['expiryDays'];
            $actorId = $this->resolveActorId($user);

            // Calculate expiry date
            $expiryDate = now()->addDays($expiryDays);

            // Generate contract number
            $fileNumber = $data['fileNumber'] ?? null;
            $contractNumber = Contract::generateContractNumber($fileNumber);

            // Prepare common data
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

            // Handle based on data source
            if ($dataSource === 'from_quotation') {
                $contractData = $this->handleFromQuotation($contractData, $data);
            } else {
                $contractData = $this->handleNewTerms($contractData, $data);
            }

            $shouldCopyMilestones = $dataSource === 'from_quotation' && ! empty($contractData['_copy_milestones']);

            unset($contractData['_copy_milestones'], $contractData['_quotation_milestones']);

            // Create the contract
            $contract = Contract::create($contractData);

            // If dataSource is 'from_quotation' and no milestones provided, copy from quotation
            if ($shouldCopyMilestones) {
                $quotation = Quotation::with('milestones')->find($data['quotationId']);
                if ($quotation && $quotation->milestones->isNotEmpty()) {
                    $this->copyMilestonesFromQuotation($contract, $quotation);
                }
            }

            // If milestones provided (for new_terms), create them
            if ($dataSource === 'new_terms' && ! empty($data['milestones'])) {
                $this->createMilestones($contract, $data['milestones']);
            }

            return $this->loadContractRelations($contract);
        });
    }

    /**
     * Create a contract from an approved quotation.
     */
    public function createFromQuotation(Quotation $quotation, array $data = [], ?User $user = null): Contract
    {
        $payload = $data;
        $payload['dataSource'] = 'from_quotation';
        $payload['quotationId'] = $quotation->id;

        return $this->createContract($payload, $user);
    }

    /**
     * Handle contract creation from an approved quotation
     *
     * @param  array  $contractData  Base contract data
     * @param  array  $data  Original request data
     * @return array Updated contract data
     */
    private function handleFromQuotation(array $contractData, array $data): array
    {
        $quotationId = $data['quotationId'] ?? null;

        if (! $quotationId) {
            throw new \InvalidArgumentException('Quotation ID is required for from_quotation data source');
        }

        // Load quotation with relationships
        $quotation = Quotation::with(['client', 'milestones'])->findOrFail($quotationId);

        // Validate quotation is approved
        if ($quotation->status !== QuotationStatus::APPROVED) {
            throw new \InvalidArgumentException('Quotation must be approved to create a contract');
        }

        if (Contract::where('quotation_id', $quotationId)->exists()) {
            throw new \InvalidArgumentException('A contract already exists for this quotation');
        }

        // Link to quotation
        $contractData['quotation_id'] = $quotationId;
        $contractData['task_id'] = $data['taskId'] ?? $quotation->task_id;
        $contractData['project_id'] = $data['projectId'] ?? null;
        $contractData['template_id'] = $data['templateId'] ?? $quotation->template_id;

        // Pull final price from quotation
        $contractData['total_amount'] = $quotation->final_price;
        $contractData['currency'] = $quotation->currency;

        // If no party data provided, use client data
        if (empty($contractData['party_two'])) {
            $contractData['party_two'] = [
                'name' => $quotation->client->display_name,
                'representative' => $quotation->client->display_name,
                'email' => $quotation->client->primary_email,
                'company' => $quotation->client->company_name,
                'phone' => $quotation->client->primary_phone ?? '',
                'title' => '',
                'address' => '',
            ];
        }

        // If no deliverables provided, create from quotation scope
        if (empty($contractData['deliverables']) && $quotation->scope_description) {
            // Convert scope description to deliverables array
            $contractData['deliverables'] = $this->extractDeliverablesFromScope($quotation->scope_description);
        }

        // Mark that we need to copy milestones
        $contractData['_copy_milestones'] = true;

        return $contractData;
    }

    /**
     * Handle contract creation with new terms (not from quotation)
     *
     * @param  array  $contractData  Base contract data
     * @param  array  $data  Original request data
     * @return array Updated contract data
     */
    private function handleNewTerms(array $contractData, array $data): array
    {
        // Set provided values
        $contractData['total_amount'] = $data['totalAmount'];
        $contractData['currency'] = $data['currency'] ?? 'SAR';
        $contractData['quotation_id'] = $data['quotationId'] ?? null;
        $contractData['task_id'] = $data['taskId'] ?? null;
        $contractData['project_id'] = $data['projectId'] ?? null;
        $contractData['template_id'] = $data['templateId'] ?? null;

        return $contractData;
    }

    /**
     * Extract deliverables from quotation scope description
     */
    private function extractDeliverablesFromScope(string $scopeDescription): array
    {
        // Simple extraction: split by newlines or bullet points
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

    /**
     * Create milestones for a contract
     */
    private function createMilestones(Contract $contract, array $milestones): void
    {
        $sortOrder = 0;

        foreach ($milestones as $milestone) {
            // Calculate amount from percentage
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

    /**
     * Update a contract and replace milestones when provided.
     */
    public function updateContract(Contract $contract, array $data, ?User $user = null): Contract
    {
        return DB::transaction(function () use ($contract, $data, $user) {
            $actorId = $this->resolveActorId($user);

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

            return $this->loadContractRelations($contract);
        });
    }

    /**
     * Copy milestones from quotation to contract
     */
    public function copyMilestonesFromQuotation(Contract $contract, Quotation $quotation): void
    {
        $milestones = $quotation->milestones;

        $sortOrder = 0;
        foreach ($milestones as $quotationMilestone) {
            // Calculate amount from percentage
            $percentage = $quotationMilestone->percentage;
            $amount = ($contract->total_amount * $percentage) / 100;

            // Calculate due date based on contract start date if not set
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

    /**
     * Generate contract draft from quotation (legacy method)
     */
    public function generateDraft(Quotation $quotation): Contract
    {
        return DB::transaction(function () use ($quotation) {
            // Load quotation with client relationship
            $quotation->load('client');

            // Generate contract content from template
            $content = $this->generateContractContent($quotation);

            // Prepare party information
            $partyOne = [
                'company' => config('app.company_name', 'Your Company'),
                'representative' => config('app.company_representative', 'Company Representative'),
                'title' => config('app.company_title', 'CEO'),
                'address' => config('app.company_address', 'Company Address'),
            ];

            $partyTwo = [
                'name' => $quotation->client->display_name,
                'email' => $quotation->client->primary_email,
                'company' => $quotation->client->company_name,
                'phone' => $quotation->client->primary_phone ?? '',
            ];

            // Create contract
            $contract = Contract::create([
                'contract_number' => Contract::generateContractNumber(),
                'quotation_id' => $quotation->id,
                'title' => "Service Agreement - {$quotation->client->display_name}",
                'content' => $content,
                'party_one' => $partyOne,
                'party_two' => $partyTwo,
                'total_amount' => $quotation->final_price,
                'currency' => $quotation->currency,
                'payment_terms' => 'Payment due within 30 days of invoice date',
                'start_date' => now()->toDateString(),
                'status' => ContractStatus::DRAFT,
                'created_by' => auth()->id(),
            ]);

            return $contract;
        });
    }

    /**
     * Sign a contract (legacy method for backward compatibility)
     */
    public function signContract(Contract $contract, string $signedByClient, User $user): Contract
    {
        return $this->markAsSigned($contract, ['signedByClient' => $signedByClient], $user);
    }

    /**
     * Mark contract as signed and activate project
     * Also activates or creates the related project.
     *
     * @param  array  $data  Data including optional signedPdf
     */
    public function markAsSigned(Contract $contract, array $data = [], ?User $user = null): Contract
    {
        return DB::transaction(function () use ($contract, $data, $user) {
            $actorId = $this->resolveActorId($user);
            // Validate contract can be signed
            if (! $contract->canBeSigned()) {
                throw new \InvalidArgumentException('Contract cannot be signed in current status');
            }

            $updateData = [
                'status' => ContractStatus::SIGNED,
                'signed_at' => now(),
                'signed_by_client' => $data['signedByClient'] ?? $contract->party_two['name'] ?? 'Client',
                'updated_by' => $actorId,
            ];

            // Handle signed PDF file if provided
            if (isset($data['signedPdf']) && $data['signedPdf'] instanceof UploadedFile) {
                $path = $data['signedPdf']->store('contracts/signed', 'private');
                // Store the PDF path - you might want to add a signed_file_path column
                // For now we'll just note it was uploaded
            }

            // Update contract
            $contract->update($updateData);
            $contract->loadMissing('quotation.client', 'project');

            // Activate or create project
            $this->activateProject($contract, $user);

            return $this->loadContractRelations($contract);
        });
    }

    /**
     * Update contract status
     */
    public function updateStatus(Contract $contract, ContractStatus $status): Contract
    {
        $contract->update([
            'status' => $status,
            'updated_by' => auth()->id(),
        ]);

        return $contract->fresh();
    }

    /**
     * Activate a signed contract.
     */
    public function activateContract(Contract $contract, ?User $user = null): Contract
    {
        if (! in_array($contract->status, [ContractStatus::SIGNED, ContractStatus::ACTIVE], true)) {
            throw new \InvalidArgumentException('Only signed contracts can be activated');
        }

        $this->activateProject($contract, $user);

        $contract->update([
            'status' => ContractStatus::ACTIVE,
            'updated_by' => $this->resolveActorId($user),
        ]);

        return $this->loadContractRelations($contract);
    }

    /**
     * Update contract status using raw string input.
     */
    public function updateContractStatus(Contract $contract, string $status, ?User $user = null): Contract
    {
        $statusEnum = ContractStatus::from($status);

        if ($statusEnum === ContractStatus::ACTIVE) {
            $this->activateProject($contract, $user);
        }

        $contract->update([
            'status' => $statusEnum,
            'updated_by' => $this->resolveActorId($user),
        ]);

        return $this->loadContractRelations($contract);
    }

    /**
     * Get milestones that are currently due.
     */
    public function getDueMilestones()
    {
        return ContractMilestone::with(['contract.quotation.client', 'invoice'])
            ->where('status', MilestoneStatus::PENDING)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', now()->toDateString())
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Mark a milestone as paid.
     */
    public function markMilestonePaid(ContractMilestone $milestone, ?int $invoiceId = null): ContractMilestone
    {
        $milestone->update([
            'status' => MilestoneStatus::PAID,
            'invoice_id' => $invoiceId ?? $milestone->invoice_id,
            'paid_at' => now(),
        ]);

        return $milestone->fresh(['contract.quotation.client', 'invoice']);
    }

    /**
     * Generate contract content from template
     */
    private function generateContractContent(Quotation $quotation): string
    {
        $template = $this->getContractTemplate();

        // Prepare payment plan table
        $paymentPlanTable = $this->generatePaymentPlanTable($quotation);

        // Prepare estimations breakdown
        $estimationsBreakdown = $this->generateEstimationsBreakdown($quotation);

        // Replace placeholders
        $replacements = [
            '{{client_name}}' => $quotation->client->name ?? 'Client',
            '{{client_email}}' => $quotation->client->email ?? '',
            '{{client_company}}' => $quotation->client->company ?? '',
            '{{total_amount}}' => number_format($quotation->final_price, 2),
            '{{currency}}' => $quotation->currency,
            '{{payment_plan}}' => $paymentPlanTable,
            '{{estimations_breakdown}}' => $estimationsBreakdown,
            '{{contract_date}}' => now()->format('F j, Y'),
            '{{hourly_rate}}' => number_format($quotation->hourly_rate, 2),
            '{{discount_percentage}}' => number_format($quotation->discount_percentage, 2),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Get contract template
     */
    private function getContractTemplate(): string
    {
        return '
<!DOCTYPE html>
<html>
<head>
    <title>Service Agreement</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 40px; }
        .header { text-align: center; margin-bottom: 30px; }
        .section { margin-bottom: 20px; }
        .table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .table th, .table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        .table th { background-color: #f2f2f2; }
        .signature { margin-top: 50px; }
        .signature-line { border-bottom: 1px solid #000; width: 200px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>SERVICE AGREEMENT</h1>
        <p>Date: {{contract_date}}</p>
    </div>

    <div class="section">
        <h2>PARTIES</h2>
        <p><strong>Service Provider:</strong> [Company Name]</p>
        <p><strong>Client:</strong> {{client_name}}</p>
        <p><strong>Company:</strong> {{client_company}}</p>
        <p><strong>Email:</strong> {{client_email}}</p>
    </div>

    <div class="section">
        <h2>PROJECT SCOPE & PRICING</h2>
        <p>The following services will be provided at an hourly rate of {{currency}} {{hourly_rate}}:</p>
        {{estimations_breakdown}}
        
        <p><strong>Total Project Cost:</strong> {{currency}} {{total_amount}}</p>
        <p><strong>Discount Applied:</strong> {{discount_percentage}}%</p>
    </div>

    <div class="section">
        <h2>PAYMENT TERMS</h2>
        {{payment_plan}}
    </div>

    <div class="section">
        <h2>TERMS & CONDITIONS</h2>
        <ul>
            <li>Payment is due within 30 days of invoice date</li>
            <li>Late payments may incur additional charges</li>
            <li>All work will be delivered according to agreed timeline</li>
            <li>Client approval is required for UI/UX deliverables</li>
            <li>Source code will be delivered upon final payment</li>
        </ul>
    </div>

    <div class="signature">
        <div style="display: flex; justify-content: space-between;">
            <div>
                <p><strong>Service Provider</strong></p>
                <div class="signature-line"></div>
                <p>Date: _____________</p>
            </div>
            <div>
                <p><strong>Client: {{client_name}}</strong></p>
                <div class="signature-line"></div>
                <p>Date: _____________</p>
            </div>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Generate payment plan table HTML
     */
    private function generatePaymentPlanTable(Quotation $quotation): string
    {
        // For now, create a simple payment plan
        // This can be enhanced later to use actual payment plan data from quotation
        $milestones = [
            ['name' => 'Project Kickoff', 'percentage' => 30],
            ['name' => 'UI/UX Completion', 'percentage' => 30],
            ['name' => 'Development Completion', 'percentage' => 30],
            ['name' => 'Final Delivery', 'percentage' => 10],
        ];

        $html = '<table class="table">';
        $html .= '<thead><tr><th>Milestone</th><th>Percentage</th><th>Amount</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($milestones as $milestone) {
            $amount = ($quotation->final_price * $milestone['percentage']) / 100;
            $html .= sprintf(
                '<tr><td>%s</td><td>%d%%</td><td>%s %s</td></tr>',
                $milestone['name'],
                $milestone['percentage'],
                $quotation->currency,
                number_format($amount, 2)
            );
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Generate estimations breakdown HTML
     */
    private function generateEstimationsBreakdown(Quotation $quotation): string
    {
        $estimations = $quotation->estimations;

        $html = '<table class="table">';
        $html .= '<thead><tr><th>Service Type</th><th>Hours</th><th>Rate</th><th>Subtotal</th></tr></thead>';
        $html .= '<tbody>';

        $total = 0;
        foreach ($estimations as $type => $hours) {
            $subtotal = $hours * $quotation->hourly_rate;
            $total += $subtotal;

            $html .= sprintf(
                '<tr><td>%s</td><td>%d</td><td>%s %s</td><td>%s %s</td></tr>',
                ucfirst(str_replace('_', ' ', $type)),
                $hours,
                $quotation->currency,
                number_format($quotation->hourly_rate, 2),
                $quotation->currency,
                number_format($subtotal, 2)
            );
        }

        // Add discount row if applicable
        if ($quotation->discount_percentage > 0) {
            $discountAmount = ($total * $quotation->discount_percentage) / 100;
            $html .= sprintf(
                '<tr><td colspan="3"><strong>Discount (%s%%)</strong></td><td><strong>-%s %s</strong></td></tr>',
                number_format($quotation->discount_percentage, 2),
                $quotation->currency,
                number_format($discountAmount, 2)
            );
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Activate project when contract is signed
     */
    private function activateProject(Contract $contract, ?User $user = null): void
    {
        $actorId = $this->resolveActorId($user);
        $contract->loadMissing('quotation.client', 'project');
        $quotation = $contract->quotation;

        // Check if project already exists
        if ($contract->project_id) {
            // Update existing project status to in progress
            $project = $contract->project;
            if ($project) {
                $project->update([
                    'status' => ProjectStatus::IN_PROGRESS,
                    'updated_by' => $actorId,
                ]);
            }

            return;
        }

        if (! $quotation) {
            return;
        } else {
            // Create new project from quotation using conversion service
            $project = $this->projectConversionService->convertQuotation($quotation->id, [
                'title' => 'Project for '.($quotation->client?->display_name ?? 'Client'),
                'description' => 'Project created from signed contract',
            ]);

            // Update project status to in progress
            $project->update([
                'status' => ProjectStatus::IN_PROGRESS,
                'updated_by' => $actorId,
            ]);

            // Link contract to project
            $contract->update(['project_id' => $project->id]);
        }
    }

    private function resolveActorId(?User $user = null): ?int
    {
        return $user?->id ?? auth()->id();
    }

    private function resolveActor(?User $user = null): ?User
    {
        $authUser = auth()->user();

        return $user ?? ($authUser instanceof User ? $authUser : null);
    }

    private function loadContractRelations(Contract $contract): Contract
    {
        return $contract->fresh()->load(['milestones.invoice', 'quotation.client', 'project', 'template']);
    }
}
