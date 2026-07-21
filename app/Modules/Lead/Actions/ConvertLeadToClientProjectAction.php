<?php

namespace App\Modules\Lead\Actions;

use App\Enums\ClientStatus;
use App\Enums\ContractModel;
use App\Enums\ContractStatus;
use App\Enums\LeadStage;
use App\Enums\LeadStatus;
use App\Enums\QuotationStatus;
use App\Modules\Client\Models\Client;
use App\Modules\Finance\Models\FinanceInvoice;
use App\Modules\Finance\Models\Subscription;
use App\Modules\Lead\Models\Contract;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\Quotation;
use App\Modules\Project\Enums\ProjectStatus;
use App\Modules\Project\Models\Project;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ConvertLeadToClientProjectAction
 *
 * Converts a won lead into a live Client + Project in a single atomic
 * transaction. All historical sales documents (proposals, quotations,
 * contracts) are migrated to reference the new client and project so
 * no negotiation history is lost.
 *
 * Edge cases handled:
 *  - No contract on the lead → project is created with a zero budget
 *    and no deadline (graceful degradation, not a hard failure).
 *  - Lead already has a client_id → the existing client is reused.
 *  - Lead already has a converted_project_id → the original conversion is returned.
 *  - SaaS contract model → budget is derived from recurring_fee;
 *    Standard model → budget is derived from total_amount or final_total.
 *
 * @return array{client: Client, project: Project}
 */
class ConvertLeadToClientProjectAction
{
    /**
     * @param  Lead  $lead  The lead that has just been moved to "won".
     * @return array{client: Client, project: Project}
     *
     * @throws DomainException If the lead cannot be converted safely.
     */
    public function execute(Lead $lead): array
    {
        return DB::transaction(function () use ($lead) {
            /** @var Lead $lead */
            $lead = Lead::query()->lockForUpdate()->findOrFail($lead->id);

            if ($lead->converted_project_id) {
                $project = Project::query()->with('client')->findOrFail($lead->converted_project_id);

                return [
                    'client' => $project->client,
                    'project' => $project,
                ];
            }

            if ($lead->stage !== LeadStage::WON) {
                throw new DomainException('Only won leads can be converted.');
            }

            if ($lead->status === LeadStatus::ARCHIVED) {
                throw new DomainException('This lead was already converted, but its original project link is unavailable.');
            }

            $client = $this->resolveClient($lead);
            $contract = $this->resolveContract($lead);
            $project = $this->createProject($lead, $client, $contract);
            $this->migrateDocuments($lead, $client, $project);
            $this->createFinanceRecords($lead, $client, $project, $contract);
            $this->reparentPolymorphicRecords($lead, $client);
            $lead->status = LeadStatus::ARCHIVED;
            $lead->client_id = $client->id;
            $lead->converted_project_id = $project->id;
            $lead->save();

            return [
                'client' => $client->fresh(),
                'project' => $project->fresh(),
            ];
        });
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * If the lead already has a client_id (e.g., it was pre-linked), reuse it.
     * Otherwise create a new Client from the lead's data.
     */
    private function resolveClient(Lead $lead): Client
    {
        if ($lead->client_id) {
            /** @var Client $existing */
            $existing = Client::findOrFail($lead->client_id);

            // Ensure the client is marked active
            if ($existing->status !== ClientStatus::ACTIVE) {
                $existing->status = ClientStatus::ACTIVE;
                $existing->became_client_at = now();
                $existing->save();
            }

            return $existing;
        }

        $leadMetadata = $lead->metadata ?? [];
        $companyName = $this->metadataString($leadMetadata, 'company_name') ?: $lead->title;
        $contactName = $this->metadataString($leadMetadata, 'contact_name');
        $primaryEmail = $this->metadataString($leadMetadata, 'primary_email');
        $primaryPhone = $this->metadataString($leadMetadata, 'primary_phone');

        $client = Client::create(array_filter([
            'type' => 'company',
            'display_name' => $companyName,
            'company_name' => $companyName,
            'primary_email' => $primaryEmail,
            'primary_phone' => $primaryPhone,
            'source' => $lead->source ?? null,
            'lead_temperature' => $lead->lead_temperature ?? null,
            'expected_budget' => $lead->expected_budget ?? null,
            'expected_close_date' => $lead->expected_close_date?->toDateString(),
            'account_manager_id' => $lead->assigned_to ?? null,
            'notes' => $lead->notes ?? null,
            'lifecycle_stage' => 'client',
            'status' => ClientStatus::ACTIVE->value,
            'became_client_at' => now()->toDateTimeString(),
            'metadata' => array_filter(array_merge($leadMetadata, [
                'converted_from_lead_id' => $lead->id,
                'original_lead_title' => $lead->title,
            ]), fn ($metadataEntry) => $metadataEntry !== null && $metadataEntry !== ''),
        ], fn ($clientAttribute) => $clientAttribute !== null));

        if ($contactName || $primaryEmail || $primaryPhone) {
            $client->contacts()->create(array_filter([
                'display_name' => $contactName ?: $companyName,
                'email' => $primaryEmail,
                'phone' => $primaryPhone,
                'is_primary' => true,
            ], fn ($contactAttribute) => $contactAttribute !== null && $contactAttribute !== ''));
        }

        return $client;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function metadataString(array $metadata, string $key): ?string
    {
        $metadataValue = $metadata[$key] ?? null;

        return is_string($metadataValue) && trim($metadataValue) !== '' ? trim($metadataValue) : null;
    }

    /**
     * Fetch the most relevant contract for this lead.
     *
     * Priority order:
     *   1. Signed contract
     *   2. Most recently created contract (any status)
     *   3. null — no contract exists (graceful degradation)
     */
    private function resolveContract(Lead $lead): ?Contract
    {
        // Prefer a signed contract; fall back to the latest one
        return $lead->contracts()
            ->orderByRaw("CASE WHEN status = 'signed' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->first();
    }

    private function resolveAcceptedQuotation(Lead $lead): ?Quotation
    {
        return $lead->quotations()
            ->where('status', QuotationStatus::ACCEPTED->value)
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Derive the project budget from the contract.
     *
     * Standard model  → total_amount (stored in payment_stages sum) or final_total
     *                   from the linked quotation.
     * SaaS model      → recurring_fee (monthly commitment).
     * No contract     → null (blank project).
     */
    private function deriveBudget(?Contract $contract): ?float
    {
        if (! $contract) {
            return null;
        }

        if ($contract->contract_model === ContractModel::SAAS_SUBSCRIPTION) {
            return $contract->recurring_fee ? (float) $contract->recurring_fee : null;
        }

        // Standard: sum payment stages if present, otherwise fall back to
        // the quotation's final_total via the lead relationship.
        if (! empty($contract->payment_stages)) {
            $sum = collect($contract->payment_stages)->sum('amount');
            if ($sum > 0) {
                return (float) $sum;
            }
        }

        // Last resort: pull from the most recent quotation
        $quotation = $contract->lead?->quotations()
            ->orderByDesc('created_at')
            ->first();

        return $quotation?->final_total ? (float) $quotation->final_total : null;
    }

    /**
     * Compute the project deadline from the contract's execution_start_date
     * and development_days (+ review_days + testing_days).
     */
    private function deriveDeadline(?Contract $contract): ?string
    {
        if (! $contract) {
            return null;
        }

        // We stored execution_start_date in the contracts table via the form
        // but the Contract model doesn't cast it — read it as a raw attribute.
        $startRaw = $contract->getAttributes()['execution_start_date'] ?? null;

        if (! $startRaw) {
            return null;
        }

        $totalDays = (int) ($contract->development_days ?? 0)
            + (int) ($contract->review_days ?? 0)
            + (int) ($contract->getAttributes()['testing_days'] ?? 0);

        if ($totalDays <= 0) {
            return null;
        }

        return Carbon::parse($startRaw)->addWeekdays($totalDays)->toDateString();
    }

    /**
     * Create the Project record.
     */
    private function createProject(Lead $lead, Client $client, ?Contract $contract): Project
    {
        $budget = $this->deriveBudget($contract);
        $deadline = $this->deriveDeadline($contract);

        return Project::create(array_filter([
            'client_id' => $client->id,
            'title' => $lead->title,
            'description' => $lead->description ?? null,
            'status' => ProjectStatus::ACTIVE->value,
            'budget' => $budget ?? 0,
            'deadline' => $deadline,
        ], fn ($v) => $v !== null));
    }

    /**
     * Bulk-update all sales documents for this lead so they reference
     * the newly created client and project. Uses a single query per
     * table for efficiency — no N+1.
     */
    private function migrateDocuments(Lead $lead, Client $client, Project $project): void
    {
        $update = [
            'client_id' => $client->id,
            'project_id' => $project->id,
        ];

        $lead->proposals()->update($update);
        $lead->quotations()->update($update);
        $lead->contracts()->update($update);
    }

    private function createFinanceRecords(Lead $lead, Client $client, Project $project, ?Contract $contract): void
    {
        $billingContract = $contract?->status === ContractStatus::SIGNED ? $contract : null;

        if ($billingContract?->contract_model === ContractModel::SAAS_SUBSCRIPTION) {
            $this->createSubscriptionFromContract($billingContract, $lead, $client, $project);

            return;
        }

        if ($billingContract && $billingContract->contract_model === ContractModel::STANDARD && is_array($billingContract->payment_stages) && $billingContract->payment_stages !== []) {
            $this->createInvoicesForPaymentStages(
                $billingContract->payment_stages,
                $project,
                $client->id,
                $this->contractTotal($billingContract),
                'contract'
            );

            return;
        }

        $quotation = $this->resolveAcceptedQuotation($lead);

        if ($quotation && is_array($quotation->payment_stages) && $quotation->payment_stages !== []) {
            $this->createInvoicesForPaymentStages(
                $quotation->payment_stages,
                $project,
                $client->id,
                (float) ($quotation->final_total ?? 0),
                'quotation'
            );
        }
    }

    /**
     * @param  array<int, mixed>  $stages
     */
    private function createInvoicesForPaymentStages(array $stages, Project $project, int $clientId, float $baseTotal, string $source): void
    {
        foreach ($stages as $index => $stage) {
            if (! is_array($stage)) {
                continue;
            }

            $amount = $this->resolveStageAmount($stage, $baseTotal);
            $stageName = $stage['name'] ?? $stage['stage_name'] ?? 'Stage '.($index + 1);
            $issueDate = now()->toDateString();

            FinanceInvoice::create([
                'client_id' => $clientId,
                'project_id' => $project->id,
                'invoice_number' => FinanceInvoice::generateInvoiceNumber(),
                'issue_date' => $issueDate,
                'due_date' => $this->resolveStageDueDate($stage['due_date'] ?? null, $issueDate),
                'subtotal' => $amount,
                'tax_rate' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => $amount,
                'status' => 'draft',
                'notes' => "Auto-created from {$source} payment stage: {$stageName}",
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $stage
     */
    private function resolveStageAmount(array $stage, float $baseTotal): float
    {
        if (isset($stage['amount']) && is_numeric($stage['amount'])) {
            return round((float) $stage['amount'], 2);
        }

        if (isset($stage['percentage']) && is_numeric($stage['percentage']) && $baseTotal > 0) {
            return round($baseTotal * ((float) $stage['percentage'] / 100), 2);
        }

        return 0.0;
    }

    private function resolveStageDueDate(mixed $dueDate, string $fallbackDate): string
    {
        if (! is_string($dueDate) || trim($dueDate) === '') {
            return $fallbackDate;
        }

        try {
            return Carbon::parse($dueDate)->toDateString();
        } catch (\Throwable) {
            return $fallbackDate;
        }
    }

    private function createSubscriptionFromContract(Contract $contract, Lead $lead, Client $client, Project $project): void
    {
        Subscription::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'name' => $lead->title,
            'provider' => $client->display_name,
            'billing_cycle' => $contract->billing_cycle ?: 'monthly',
            'cost' => (float) ($contract->recurring_fee ?? 0),
            'currency' => $contract->getAttribute('currency') ?: 'USD',
            'next_billing_date' => $this->resolveSubscriptionStartDate($contract),
            'status' => 'active',
            'notes' => 'Auto-created from SaaS contract during lead conversion.',
        ]);
    }

    private function resolveSubscriptionStartDate(Contract $contract): string
    {
        $startDate = $contract->getAttribute('subscription_start_date');

        if ($startDate) {
            return Carbon::parse($startDate)->toDateString();
        }

        return now()->toDateString();
    }

    private function contractTotal(Contract $contract): float
    {
        if ($contract->total_amount) {
            return (float) $contract->total_amount;
        }

        if (is_array($contract->payment_stages)) {
            return (float) collect($contract->payment_stages)->sum('amount');
        }

        return 0.0;
    }

    /**
     * Move polymorphic records that were attached to the lead onto the newly
     * created client, preserving activity history after conversion.
     *
     * Also re-parents any FinanceInvoice records (client_id FK, not polymorphic)
     * that were created during the lead/proposal stage so they are not lost.
     */
    private function reparentPolymorphicRecords(Lead $lead, Client $client): void
    {
        foreach (['meetings', 'notes', 'files', 'tasks', 'invoices'] as $table) {
            $this->reparentPolymorphicTable($table, $lead, $client);
        }

        // Re-parent FinanceInvoice records that reference the lead's project
        // (or any invoice whose client_id is still null / unset).
        // We match on project_id because FinanceInvoice uses a direct FK, not
        // a polymorphic relationship.
        if (Schema::hasTable('finance_invoices') && Schema::hasColumn('finance_invoices', 'client_id')) {
            // Case 1: invoices already linked to a project that belongs to this lead
            DB::table('finance_invoices')
                ->whereNull('client_id')
                ->whereIn('project_id', function ($q) use ($lead) {
                    $q->select('id')
                        ->from('projects')
                        ->where('client_id', $lead->client_id ?? 0);
                })
                ->update([
                    'client_id' => $client->id,
                    'updated_at' => now(),
                ]);

            // Case 2: invoices explicitly tagged with the lead's old client_id
            // (covers pre-conversion invoices created against a prospect client)
            if ($lead->client_id && $lead->client_id !== $client->id) {
                DB::table('finance_invoices')
                    ->where('client_id', $lead->client_id)
                    ->update([
                        'client_id' => $client->id,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    private function reparentPolymorphicTable(string $table, Lead $lead, Client $client): void
    {
        if (
            ! Schema::hasTable($table)
            || ! Schema::hasColumn($table, 'model_type')
            || ! Schema::hasColumn($table, 'model_id')
        ) {
            return;
        }

        DB::table($table)
            ->where('model_type', Lead::class)
            ->where('model_id', $lead->id)
            ->update([
                'model_type' => Client::class,
                'model_id' => $client->id,
                'updated_at' => now(),
            ]);
    }
}
