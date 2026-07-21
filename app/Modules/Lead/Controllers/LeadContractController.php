<?php

namespace App\Modules\Lead\Controllers;

use App\Enums\ContractModel;
use App\Enums\ContractStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Lead\Actions\StoreLeadDocumentFileAction;
use App\Modules\Lead\Models\Contract;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Requests\StoreLeadContractRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Manages contracts attached directly to a lead from the Details Page.
 *
 * Payload shape mirrors stage_results[*] for type=contract so the
 * frontend ContractDirectUploadTab + mapStageResultToPayload can be
 * reused without modification.
 */
class LeadContractController extends Controller
{
    public function __construct(
        private readonly StoreLeadDocumentFileAction $storeDocumentFile,
    ) {}

    /**
     * List all contracts for a lead.
     *
     * GET /api/v1/leads/{lead}/contracts
     */
    public function index(Request $request, Lead $lead): JsonResponse
    {
        abort_unless($request->user()?->canAny(['view_contracts', 'manage_contracts']), 403, 'Unauthorized');

        $contracts = $lead->contracts()
            ->with('assignee:id,name,email,avatar')
            ->orderByDesc('created_at')
            ->get();

        $data = $contracts->map(fn (Contract $c) => $this->formatContract($c));

        return ApiResponse::success($data, 'Contracts retrieved successfully.');
    }

    /**
     * Create a new contract directly on a lead.
     *
     * POST /api/v1/leads/{lead}/contracts
     */
    public function store(StoreLeadContractRequest $request, Lead $lead): JsonResponse
    {
        abort_unless($request->user()?->canAny(['create_contracts', 'manage_contracts']), 403, 'Unauthorized');

        $data = $request->validated();

        if (($data['status'] ?? null) === ContractStatus::SIGNED->value) {
            abort_unless($request->user()->canAny(['sign_contracts', 'manage_contracts']), 403, 'Unauthorized');
        }
        $fileAttributes = $request->hasFile('upload_file')
            ? $this->storeDocumentFile->execute($lead, $request->file('upload_file'), 'contract')
            : [];

        try {
            $contract = Contract::create(array_merge([
                'lead_id' => $lead->id,
                'client_id' => $lead->client_id,
                'project_id' => null,
                'status' => $data['status'] ?? ContractStatus::DRAFT->value,
                'creation_type' => $data['creation_type'],
                'skip_reason' => $data['skip_reason'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
                'contract_model' => $data['contract_model'] ?? ContractModel::STANDARD->value,
                'development_days' => $data['development_days'] ?? null,
                'review_days' => $data['review_days'] ?? null,
                'testing_days' => $data['testing_days'] ?? null,
                'delay_penalty' => $data['delay_penalty'] ?? null,
                'source_code_handover' => $data['source_code_handover'] ?? false,
                'execution_start_date' => $data['execution_start_date'] ?? null,
                'working_days_per_week' => $data['working_days_per_week'] ?? null,
                // Standard
                'currency' => $data['currency'] ?? null,
                'total_amount' => $data['total_amount'] ?? null,
                'payment_stages' => $data['payment_stages'] ?? null,
                // SaaS
                'setup_fee' => $data['setup_fee'] ?? null,
                'recurring_fee' => $data['recurring_fee'] ?? null,
                'billing_cycle' => $data['billing_cycle'] ?? null,
                'subscription_start_date' => $data['subscription_start_date'] ?? null,
                'apply_promo' => $data['apply_promo'] ?? false,
                'promo_discount' => $data['promo_discount'] ?? null,
                'addons' => $data['addons'] ?? null,
                // Shared
                'notes' => $data['notes'] ?? null,
            ], $fileAttributes));
        } catch (Throwable $exception) {
            $this->storeDocumentFile->delete($fileAttributes['file_path'] ?? null);

            throw $exception;
        }

        $contract->load('assignee:id,name,email,avatar');

        return ApiResponse::success(
            $this->formatContract($contract),
            'Contract created successfully.',
            201
        );
    }

    /**
     * Update editable contract fields from the lead details table.
     */
    public function update(Request $request, Lead $lead, Contract $contract): JsonResponse
    {
        abort_unless($request->user()?->canAny(['edit_contracts', 'manage_contracts']), 403, 'Unauthorized');
        $this->assertBelongsToLead($contract, $lead);

        $data = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(ContractStatus::values())],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if (($data['status'] ?? null) === ContractStatus::SIGNED->value) {
            abort_unless($request->user()->canAny(['sign_contracts', 'manage_contracts']), 403, 'Unauthorized');
        }

        $contract->update($data);
        $contract->load('assignee:id,name,email,avatar');

        return ApiResponse::success(
            $this->formatContract($contract),
            'Contract updated successfully.'
        );
    }

    public function sign(Request $request, Lead $lead, Contract $contract): JsonResponse
    {
        abort_unless($request->user()?->canAny(['sign_contracts', 'manage_contracts']), 403, 'Unauthorized');
        $this->assertBelongsToLead($contract, $lead);

        if ($contract->status !== ContractStatus::SIGNED) {
            abort_unless(
                in_array($contract->status, [ContractStatus::DRAFT, ContractStatus::SENT], true),
                422,
                'Only draft or sent contracts can be signed.'
            );

            $contract->update(['status' => ContractStatus::SIGNED]);
        }

        return ApiResponse::success(
            $this->formatContract($contract->fresh()->load('assignee:id,name,email,avatar')),
            'Contract signed successfully.'
        );
    }

    public function destroy(Request $request, Lead $lead, Contract $contract): JsonResponse
    {
        abort_unless($request->user()?->canAny(['delete_contracts', 'manage_contracts']), 403, 'Unauthorized');
        $this->assertBelongsToLead($contract, $lead);

        $contract->delete();

        return ApiResponse::success(null, 'Contract deleted successfully.');
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private function formatContract(Contract $c): array
    {
        return [
            'id' => $c->id,
            'status' => $c->status->value,
            'creation_type' => $c->creation_type->value,
            'skip_reason' => $c->skip_reason,
            'file_path' => $c->file_path ? route('leads.documents.download', [
                'lead' => $c->lead_id,
                'documentType' => 'contract',
                'documentId' => $c->id,
            ]) : null,
            'file_name' => $c->file_name,
            'file_mime_type' => $c->file_mime_type,
            'file_size' => $c->file_size,
            'contract_model' => $c->contract_model?->value,
            'development_days' => $c->development_days,
            'review_days' => $c->review_days,
            'testing_days' => $c->testing_days,
            'delay_penalty' => $c->delay_penalty !== null ? (float) $c->delay_penalty : null,
            'source_code_handover' => (bool) $c->source_code_handover,
            'execution_start_date' => $c->execution_start_date?->toDateString(),
            'working_days_per_week' => $c->working_days_per_week,
            // Standard
            'currency' => $c->currency ?? null,
            'total_amount' => $c->total_amount !== null ? (float) $c->total_amount : null,
            'payment_stages' => $c->payment_stages ?? [],
            // SaaS
            'setup_fee' => $c->setup_fee !== null ? (float) $c->setup_fee : null,
            'recurring_fee' => $c->recurring_fee !== null ? (float) $c->recurring_fee : null,
            'billing_cycle' => $c->billing_cycle,
            'subscription_start_date' => $c->subscription_start_date?->toDateString(),
            'apply_promo' => (bool) $c->apply_promo,
            'promo_discount' => $c->promo_discount !== null ? (float) $c->promo_discount : null,
            'addons' => $c->addons ?? [],
            // Shared
            'notes' => $c->notes,
            'assigned_to' => $c->assigned_to,
            'personal_task_id' => $c->personal_task_id,
            'assignee' => $c->assignee ? [
                'id' => $c->assignee->id,
                'name' => $c->assignee->name,
                'email' => $c->assignee->email,
                'avatar' => $c->assignee->avatar,
            ] : null,
            'created_at' => $c->created_at?->toISOString(),
            'updated_at' => $c->updated_at?->toISOString(),
        ];
    }

    private function assertBelongsToLead(Contract $contract, Lead $lead): void
    {
        if ((int) $contract->lead_id !== (int) $lead->id) {
            abort(404, 'Contract not found for this lead.');
        }
    }
}
