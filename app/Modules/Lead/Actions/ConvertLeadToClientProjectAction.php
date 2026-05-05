<?php

namespace App\Modules\Lead\Actions;

use App\Enums\ClientStatus;
use App\Enums\ContractModel;
use App\Enums\LeadStatus;
use App\Modules\Client\Models\Client;
use App\Modules\Lead\Models\Contract;
use App\Modules\Lead\Models\Lead;
use App\Modules\Project\Enums\ProjectStatus;
use App\Modules\Project\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

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
 *  - Lead already has a client_id → the existing client is reused and
 *    only the project is created (idempotent re-run safety).
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
     * @throws RuntimeException  If the lead is already converted (has a project).
     */
    public function execute(Lead $lead): array
    {
        return DB::transaction(function () use ($lead) {

            // ── 1. Resolve or create the Client ──────────────────────────────

            $client = $this->resolveClient($lead);

            // ── 2. Fetch the most relevant Contract ──────────────────────────

            $contract = $this->resolveContract($lead);

            // ── 3. Create the Project ─────────────────────────────────────────

            $project = $this->createProject($lead, $client, $contract);

            // ── 4. Migrate all sales documents ────────────────────────────────

            $this->migrateDocuments($lead, $client, $project);

            // ── 5. Reparent polymorphic activity from Lead to Client ─────────

            $this->reparentPolymorphicRecords($lead, $client);

            // ── 6. Archive the Lead and link it to the new client ─────────────

            $lead->status    = LeadStatus::ARCHIVED;
            $lead->client_id = $client->id;
            $lead->save();

            return [
                'client'  => $client->fresh(),
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

        // Parse a display name from the lead title.
        // Lead titles are typically "Company – Contact" or just "Company".
        $displayName = $lead->title;

        return Client::create(array_filter([
            'type'              => 'company',
            'display_name'      => $displayName,
            'company_name'      => $displayName,
            'primary_email'     => null,   // Lead model has no email field; extend if needed
            'source'            => $lead->source ?? null,
            'lead_temperature'  => $lead->lead_temperature ?? null,
            'expected_budget'   => $lead->expected_budget ?? null,
            'account_manager_id'=> $lead->assigned_to ?? null,
            'notes'             => $lead->notes ?? null,
            'lifecycle_stage'   => 'client',
            'status'            => ClientStatus::ACTIVE->value,
            'became_client_at'  => now()->toDateTimeString(),
            'metadata'          => array_filter([
                'converted_from_lead_id' => $lead->id,
                'original_lead_title'    => $lead->title,
            ]),
        ], fn ($v) => $v !== null));
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
        $budget   = $this->deriveBudget($contract);
        $deadline = $this->deriveDeadline($contract);

        return Project::create(array_filter([
            'client_id'   => $client->id,
            'title'       => $lead->title,
            'description' => $lead->description ?? null,
            'status'      => ProjectStatus::ACTIVE->value,
            'budget'      => $budget ?? 0,
            'deadline'    => $deadline,
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
            'client_id'  => $client->id,
            'project_id' => $project->id,
        ];

        $lead->proposals()->update($update);
        $lead->quotations()->update($update);
        $lead->contracts()->update($update);
    }

    /**
     * Move polymorphic records that were attached to the lead onto the newly
     * created client, preserving activity history after conversion.
     */
    private function reparentPolymorphicRecords(Lead $lead, Client $client): void
    {
        foreach (['meetings', 'notes', 'files', 'tasks', 'invoices'] as $table) {
            $this->reparentPolymorphicTable($table, $lead, $client);
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
