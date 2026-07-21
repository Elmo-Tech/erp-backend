<?php

namespace App\Modules\Lead\Requests;

use App\Enums\ContractStatus;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Validates a direct-creation contract for a lead.
 *
 * POST /api/v1/leads/{lead}/contracts
 *
 * Accepts the EXACT same field shape as the pipeline's stage_results entry
 * for type=contract, so the frontend can reuse ContractDirectUploadTab
 * and the existing payload mapper without any changes.
 */
class StoreLeadContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'creation_type' => ['required', 'string', 'in:direct_upload,task,skipped'],
            'skip_reason' => ['nullable', 'string', 'max:500'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'upload_file' => ['required_if:creation_type,direct_upload', 'nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:51200'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', Rule::enum(ContractStatus::class)],

            // Contract base fields
            'contract_model' => ['nullable', 'string', 'in:standard,saas_subscription'],
            'development_days' => ['nullable', 'integer', 'min:1'],
            'review_days' => ['nullable', 'integer', 'min:0'],
            'testing_days' => ['nullable', 'integer', 'min:0'],
            'delay_penalty' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'source_code_handover' => ['nullable', 'boolean'],
            'execution_start_date' => ['nullable', 'date'],
            'working_days_per_week' => ['nullable', 'integer', 'min:1', 'max:7'],

            // Standard financials
            'currency' => ['nullable', 'string', 'max:10'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_stages' => ['nullable', 'array'],
            'payment_stages.*.stage_name' => ['required_with:payment_stages', 'string', 'max:255'],
            'payment_stages.*.percentage' => ['required_with:payment_stages', 'numeric', 'min:0', 'max:100'],
            'payment_stages.*.amount' => ['required_with:payment_stages', 'numeric', 'min:0'],

            // SaaS financials
            'setup_fee' => ['nullable', 'numeric', 'min:0'],
            'recurring_fee' => ['nullable', 'numeric', 'min:0'],
            'billing_cycle' => ['nullable', 'string', 'in:monthly,quarterly,semi_annual,annual'],
            'subscription_start_date' => ['nullable', 'date'],
            'apply_promo' => ['nullable', 'boolean'],
            'promo_discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'addons' => ['nullable', 'array'],
            'addons.*.name' => ['required_with:addons', 'string', 'max:255'],
            'addons.*.price' => ['required_with:addons', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'creation_type.required' => 'The creation type is required.',
            'creation_type.in' => 'Creation type must be one of: direct_upload, task, skipped.',
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
