<?php

namespace App\Modules\Lead\Controllers;

use App\Enums\ContractModel;
use App\Enums\ContractStatus;
use App\Enums\DocumentCreationType;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Lead\Models\Contract;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Requests\StoreLeadContractRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Manages contracts attached directly to a lead from the Details Page.
 *
 * Payload shape mirrors stage_results[*] for type=contract so the
 * frontend ContractDirectUploadTab + mapStageResultToPayload can be
 * reused without modification.
 */
class LeadContractController extends Controller
{
    /**
     * List all contracts for a lead.
     *
     * GET /api/v1/leads/{lead}/contracts
     */
    public function index(Lead $lead): JsonResponse
    {
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
        $data = $request->validated();

        $contract = Contract::create([
            'lead_id'               => $lead->id,
            'client_id'             => $lead->client_id,
            'project_id'            => null,
            'status'                => $data['status'] ?? ContractStatus::DRAFT->value,
            'creation_type'         => $data['creation_type'],
            'skip_reason'           => $data['skip_reason'] ?? null,
            'assigned_to'           => $data['assigned_to'] ?? null,
            'file_path'             => $data['file_path'] ?? null,
            'contract_model'        => $data['contract_model'] ?? ContractModel::STANDARD->value,
            'development_days'      => $data['development_days'] ?? null,
            'review_days'           => $data['review_days'] ?? null,
            'testing_days'          => $data['testing_days'] ?? null,
            'delay_penalty'         => $data['delay_penalty'] ?? null,
            'source_code_handover'  => $data['source_code_handover'] ?? false,
            'execution_start_date'  => $data['execution_start_date'] ?? null,
            'working_days_per_week' => $data['working_days_per_week'] ?? null,
            // Standard
            'currency'              => $data['currency'] ?? null,
            'total_amount'          => $data['total_amount'] ?? null,
            'payment_stages'        => $data['payment_stages'] ?? null,
            // SaaS
            'setup_fee'               => $data['setup_fee'] ?? null,
            'recurring_fee'           => $data['recurring_fee'] ?? null,
            'billing_cycle'           => $data['billing_cycle'] ?? null,
            'subscription_start_date' => $data['subscription_start_date'] ?? null,
            'apply_promo'             => $data['apply_promo'] ?? false,
            'promo_discount'          => $data['promo_discount'] ?? null,
            'addons'                  => $data['addons'] ?? null,
            // Shared
            'notes'                   => $data['notes'] ?? null,
        ]);

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
        $this->assertBelongsToLead($contract, $lead);

        $data = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(ContractStatus::values())],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'file_path' => ['nullable', 'string', 'max:1000'],
        ]);

        $contract->update($data);
        $contract->load('assignee:id,name,email,avatar');

        return ApiResponse::success(
            $this->formatContract($contract),
            'Contract updated successfully.'
        );
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private function formatContract(Contract $c): array
    {
        return [
            'id'                    => $c->id,
            'status'                => $c->status->value,
            'creation_type'         => $c->creation_type->value,
            'skip_reason'           => $c->skip_reason,
            'file_path'             => $c->file_path,
            'contract_model'        => $c->contract_model?->value,
            'development_days'      => $c->development_days,
            'review_days'           => $c->review_days,
            'testing_days'          => $c->testing_days,
            'delay_penalty'         => $c->delay_penalty !== null ? (float) $c->delay_penalty : null,
            'source_code_handover'  => (bool) $c->source_code_handover,
            'execution_start_date'  => $c->execution_start_date?->toDateString(),
            'working_days_per_week' => $c->working_days_per_week,
            // Standard
            'currency'              => $c->currency ?? null,
            'total_amount'          => $c->total_amount !== null ? (float) $c->total_amount : null,
            'payment_stages'        => $c->payment_stages ?? [],
            // SaaS
            'setup_fee'               => $c->setup_fee !== null ? (float) $c->setup_fee : null,
            'recurring_fee'           => $c->recurring_fee !== null ? (float) $c->recurring_fee : null,
            'billing_cycle'           => $c->billing_cycle,
            'subscription_start_date' => $c->subscription_start_date?->toDateString(),
            'apply_promo'             => (bool) $c->apply_promo,
            'promo_discount'          => $c->promo_discount !== null ? (float) $c->promo_discount : null,
            'addons'                  => $c->addons ?? [],
            // Shared
            'notes'                   => $c->notes,
            'assigned_to'             => $c->assigned_to,
            'assignee'                => $c->assignee ? [
                'id'     => $c->assignee->id,
                'name'   => $c->assignee->name,
                'email'  => $c->assignee->email,
                'avatar' => $c->assignee->avatar,
            ] : null,
            'created_at'            => $c->created_at?->toISOString(),
            'updated_at'            => $c->updated_at?->toISOString(),
        ];
    }

    private function assertBelongsToLead(Contract $contract, Lead $lead): void
    {
        if ((int) $contract->lead_id !== (int) $lead->id) {
            abort(404, 'Contract not found for this lead.');
        }
    }
}
