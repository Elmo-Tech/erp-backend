<?php

namespace App\Modules\Lead\Actions;

use App\Enums\DocumentCreationType;
use App\Enums\LeadStage;
use App\Modules\Lead\Models\Contract;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\Proposal;
use App\Modules\Lead\Models\Quotation;
use App\Modules\PersonalTasks\Models\PersonalTask;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Processes a drag-and-drop pipeline stage update.
 *
 * Receives the lead ID, the new stage, and an array of stage results
 * (documents produced during the stage transition: proposals, quotations,
 * contracts). Creates each document record inside a single transaction,
 * then advances the lead's stage.
 *
 * NOTE: Lead → Client conversion is intentionally NOT handled here.
 *       That belongs in a dedicated ConvertLeadToClientAction.
 */
class ProcessLeadStageUpdateAction
{
    public function __construct(
        private readonly StoreLeadDocumentFileAction $storeDocumentFile,
    ) {}

    /**
     * Execute the pipeline stage update.
     *
     * @param  int  $leadId  The lead being moved.
     * @param  LeadStage  $newStage  The destination stage.
     * @param  array<int, array<string, mixed>>  $stageResults  Documents to create.
     *
     * Expected shape of each $stageResults item:
     * [
     *   'stage'         => 'meeting' | 'proposal' | 'quotation' | 'contract',
     *   'type'          => 'proposal' | 'quotation' | 'contract',
     *   'creation_type' => 'task' | 'direct_upload' | 'skipped',
     *   'skip_reason'   => string|null,
     *   'assigned_to'   => int|null,
     *   'due_date'      => string|null,
     *   'upload_file'   => UploadedFile|null,
     *   'status'        => string|null,   // defaults to 'draft'
     *
     *   // Quotation-only
     *   'currency'        => string|null,
     *   'base_price'      => float|null,
     *   'commission_type' => string|null,
     *   'final_total'     => float|null,
     *   'payment_stages'  => array|null,
     *
     *   // Contract-only
     *   'contract_model'       => 'standard' | 'saas_subscription' | null,
     *   'development_days'     => int|null,
     *   'review_days'          => int|null,
     *   'delay_penalty'        => float|null,
     *   'source_code_handover' => bool|null,
     *   'setup_fee'            => float|null,
     *   'recurring_fee'        => float|null,
     *   'billing_cycle'        => string|null,
     *   'payment_stages'       => array|null,
     * ]
     * @return Lead The updated lead with its new stage.
     *
     * @throws ModelNotFoundException If lead not found.
     * @throws InvalidArgumentException If a stage result has an unknown type or missing required fields.
     */
    public function execute(int $leadId, LeadStage $newStage, array $stageResults): Lead
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($leadId, $newStage, $stageResults, &$storedPaths) {
                /** @var Lead $lead */
                $lead = Lead::findOrFail($leadId);

                foreach ($stageResults as $index => $result) {
                    $this->validateResult($result, $index);
                    $this->createDocument($lead, $result, $storedPaths);
                }

                $lead->stage = $newStage;
                $lead->save();

                return $lead->fresh();
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);

            throw $exception;
        }
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    /**
     * Validate a single stage result entry.
     *
     * @param  array<string, mixed>  $result
     *
     * @throws InvalidArgumentException
     */
    private function validateResult(array $result, int $index): void
    {
        $allowedTypes = ['proposal', 'quotation', 'contract'];

        if (empty($result['type']) || ! in_array($result['type'], $allowedTypes, true)) {
            throw new InvalidArgumentException(
                "stage_results[{$index}].type must be one of: ".implode(', ', $allowedTypes).
                '. Got: '.($result['type'] ?? 'null')
            );
        }

        $allowedCreationTypes = DocumentCreationType::values();

        if (empty($result['creation_type']) || ! in_array($result['creation_type'], $allowedCreationTypes, true)) {
            throw new InvalidArgumentException(
                "stage_results[{$index}].creation_type must be one of: ".implode(', ', $allowedCreationTypes).
                '. Got: '.($result['creation_type'] ?? 'null')
            );
        }

        if ($result['creation_type'] === DocumentCreationType::SKIPPED->value && empty($result['skip_reason'])) {
            throw new InvalidArgumentException(
                "stage_results[{$index}].skip_reason is required when creation_type is 'skipped'."
            );
        }
    }

    /**
     * Dispatch to the correct document creator based on type.
     *
     * @param  array<string, mixed>  $result
     */
    private function createDocument(Lead $lead, array $result, array &$storedPaths): void
    {
        if ($result['creation_type'] === DocumentCreationType::TASK->value) {
            $result['personal_task_id'] = $this->createPersonalTask($lead, $result)->id;
        }

        if (($result['upload_file'] ?? null) instanceof UploadedFile) {
            $fileAttributes = $this->storeDocumentFile->execute($lead, $result['upload_file'], $result['type']);
            $result = array_merge($result, $fileAttributes);
            $storedPaths[] = $fileAttributes['file_path'];
        }

        match ($result['type']) {
            'proposal' => $this->createProposal($lead, $result),
            'quotation' => $this->createQuotation($lead, $result),
            'contract' => $this->createContract($lead, $result),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createPersonalTask(Lead $lead, array $data): PersonalTask
    {
        $stage = LeadStage::from($data['stage']);

        return PersonalTask::create([
            'user_id' => $data['assigned_to'],
            'title' => "Lead: {$lead->title} — {$stage->label()}",
            'description' => $data['notes'] ?? null,
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => $data['due_date'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createProposal(Lead $lead, array $data): Proposal
    {
        return Proposal::create([
            'lead_id' => $lead->id,
            'client_id' => $lead->client_id,
            'project_id' => null, // not yet assigned at this stage
            'status' => $data['status'] ?? 'draft',
            'creation_type' => $data['creation_type'],
            'skip_reason' => $data['skip_reason'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'personal_task_id' => $data['personal_task_id'] ?? null,
            'file_path' => $data['file_path'] ?? null,
            'file_name' => $data['file_name'] ?? null,
            'file_mime_type' => $data['file_mime_type'] ?? null,
            'file_size' => $data['file_size'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createQuotation(Lead $lead, array $data): Quotation
    {
        return Quotation::create([
            'lead_id' => $lead->id,
            'client_id' => $lead->client_id,
            'project_id' => null,
            'status' => $data['status'] ?? 'draft',
            'creation_type' => $data['creation_type'],
            'skip_reason' => $data['skip_reason'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'personal_task_id' => $data['personal_task_id'] ?? null,
            'file_path' => $data['file_path'] ?? null,
            'file_name' => $data['file_name'] ?? null,
            'file_mime_type' => $data['file_mime_type'] ?? null,
            'file_size' => $data['file_size'] ?? null,
            'currency' => $data['currency'] ?? 'USD',
            'base_price' => $data['base_price'] ?? null,
            'commission_type' => $data['commission_type'] ?? null,
            'final_total' => $data['final_total'] ?? null,
            'payment_stages' => $data['payment_stages'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createContract(Lead $lead, array $data): Contract
    {
        return Contract::create([
            'lead_id' => $lead->id,
            'client_id' => $lead->client_id,
            'project_id' => null,
            'status' => $data['status'] ?? 'draft',
            'creation_type' => $data['creation_type'],
            'skip_reason' => $data['skip_reason'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'personal_task_id' => $data['personal_task_id'] ?? null,
            'file_path' => $data['file_path'] ?? null,
            'file_name' => $data['file_name'] ?? null,
            'file_mime_type' => $data['file_mime_type'] ?? null,
            'file_size' => $data['file_size'] ?? null,
            'contract_model' => $data['contract_model'] ?? 'standard',
            'development_days' => $data['development_days'] ?? null,
            'review_days' => $data['review_days'] ?? null,
            'delay_penalty' => $data['delay_penalty'] ?? null,
            'source_code_handover' => $data['source_code_handover'] ?? false,
            'setup_fee' => $data['setup_fee'] ?? null,
            'recurring_fee' => $data['recurring_fee'] ?? null,
            'billing_cycle' => $data['billing_cycle'] ?? null,
            'payment_stages' => $data['payment_stages'] ?? null,
        ]);
    }
}
