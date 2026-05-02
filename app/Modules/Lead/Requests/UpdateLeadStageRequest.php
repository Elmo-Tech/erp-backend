<?php

namespace App\Modules\Lead\Requests;

use App\Enums\DocumentCreationType;
use App\Enums\LeadStage;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateLeadStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $creationTypes = DocumentCreationType::values();
        $documentTypes = ['proposal', 'quotation', 'contract'];

        return [
            // ── Top-level ─────────────────────────────────────────────────────
            'new_stage'    => ['required', 'string', Rule::enum(LeadStage::class)],
            'stage_results' => ['present', 'array'],

            // ── Each stage result entry ───────────────────────────────────────
            'stage_results.*.type'          => ['required', 'string', Rule::in($documentTypes)],
            'stage_results.*.creation_type' => ['required', 'string', Rule::in($creationTypes)],
            'stage_results.*.skip_reason'   => [
                'nullable',
                'string',
                'max:500',
                // Required when creation_type is 'skipped'
                Rule::requiredIf(function () {
                    // We validate this at the action level; here we just allow it through.
                    return false;
                }),
            ],
            'stage_results.*.status'        => ['nullable', 'string', 'max:50'],
            'stage_results.*.assigned_to'   => ['nullable', 'integer', 'exists:users,id'],
            'stage_results.*.file_path'     => ['nullable', 'string', 'max:1000'],
            'stage_results.*.notes'         => ['nullable', 'string', 'max:5000'],

            // ── Meeting-specific ──────────────────────────────────────────────
            'stage_results.*.meeting_type'        => ['nullable', 'string', 'max:50'],
            'stage_results.*.date'                => ['nullable', 'date'],
            'stage_results.*.time'                => ['nullable', 'string', 'max:10'],
            'stage_results.*.location_type'       => ['nullable', 'string', 'in:online,client_site,our_office,other'],
            'stage_results.*.location_details'    => ['nullable', 'string', 'max:1000'],
            'stage_results.*.internal_attendees'  => ['nullable', 'array'],
            'stage_results.*.internal_attendees.*'=> ['string'],
            'stage_results.*.external_attendees'  => ['nullable', 'array'],
            'stage_results.*.external_attendees.*'=> ['string'],

            // ── Proposal-specific ─────────────────────────────────────────────
            'stage_results.*.proposal_source' => ['nullable', 'string', 'in:upload_new,use_template'],
            'stage_results.*.template_id'     => ['nullable', 'integer', 'min:1'],
            'stage_results.*.validity_date'   => ['nullable', 'date'],

            // ── Quotation-specific ────────────────────────────────────────────
            'stage_results.*.currency'        => ['nullable', 'string', 'max:10'],
            'stage_results.*.base_price'      => ['nullable', 'numeric', 'min:0'],
            'stage_results.*.commission_type' => ['nullable', 'string', 'in:percentage,fixed'],
            'stage_results.*.final_total'     => ['nullable', 'numeric', 'min:0'],
            'stage_results.*.payment_stages'  => ['nullable', 'array'],
            'stage_results.*.payment_stages.*.stage_name' => ['required_with:stage_results.*.payment_stages', 'string', 'max:255'],
            'stage_results.*.payment_stages.*.percentage' => ['required_with:stage_results.*.payment_stages', 'numeric', 'min:0', 'max:100'],
            'stage_results.*.payment_stages.*.amount'     => ['required_with:stage_results.*.payment_stages', 'numeric', 'min:0'],

            // ── Contract-specific ─────────────────────────────────────────────
            'stage_results.*.contract_model'       => ['nullable', 'string', 'in:standard,saas_subscription'],
            'stage_results.*.development_days'     => ['nullable', 'integer', 'min:1'],
            'stage_results.*.review_days'          => ['nullable', 'integer', 'min:0'],
            'stage_results.*.testing_days'         => ['nullable', 'integer', 'min:0'],
            'stage_results.*.delay_penalty'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stage_results.*.source_code_handover' => ['nullable', 'boolean'],
            'stage_results.*.execution_start_date' => ['nullable', 'date'],
            'stage_results.*.working_days_per_week'=> ['nullable', 'integer', 'min:1', 'max:7'],

            // Contract SaaS-specific
            'stage_results.*.setup_fee'             => ['nullable', 'numeric', 'min:0'],
            'stage_results.*.recurring_fee'         => ['nullable', 'numeric', 'min:0'],
            'stage_results.*.billing_cycle'         => ['nullable', 'string', 'in:monthly,quarterly,semi_annual,annual'],
            'stage_results.*.subscription_start_date' => ['nullable', 'date'],
            'stage_results.*.apply_promo'           => ['nullable', 'boolean'],
            'stage_results.*.promo_discount'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stage_results.*.addons'                => ['nullable', 'array'],
            'stage_results.*.addons.*.name'         => ['required_with:stage_results.*.addons', 'string', 'max:255'],
            'stage_results.*.addons.*.price'        => ['required_with:stage_results.*.addons', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'new_stage.required' => 'The destination stage is required.',
            'new_stage.Illuminate\Validation\Rules\Enum' => 'The provided stage is not a valid pipeline stage.',
            'stage_results.present' => 'The stage_results field must be present (use an empty array if none).',
            'stage_results.*.type.required' => 'Each stage result must specify a document type.',
            'stage_results.*.type.in' => 'Document type must be one of: proposal, quotation, contract.',
            'stage_results.*.creation_type.required' => 'Each stage result must specify a creation type.',
            'stage_results.*.creation_type.in' => 'Creation type must be one of: task, direct_upload, skipped.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(
                __('validation.failed'),
                422,
                $validator->errors()->toArray()
            )
        );
    }
}
