<?php

namespace App\Modules\Lead\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validates a direct-creation quotation for a lead.
 *
 * POST /api/v1/leads/{lead}/quotations
 *
 * Accepts the EXACT same field shape as the pipeline's stage_results entry
 * for type=quotation, so the frontend can reuse QuotationDirectUploadTab
 * and the existing payload mapper without any changes.
 */
class StoreLeadQuotationRequest extends FormRequest
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
            'status' => ['nullable', 'string', 'max:50'],

            // Quotation financials
            'currency' => ['nullable', 'string', 'max:10'],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'commission_type' => ['nullable', 'string', 'in:percentage,fixed'],
            'final_total' => ['nullable', 'numeric', 'min:0'],

            // Payment stages
            'payment_stages' => ['nullable', 'array'],
            'payment_stages.*.stage_name' => ['required_with:payment_stages', 'string', 'max:255'],
            'payment_stages.*.percentage' => ['required_with:payment_stages', 'numeric', 'min:0', 'max:100'],
            'payment_stages.*.amount' => ['required_with:payment_stages', 'numeric', 'min:0'],
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
