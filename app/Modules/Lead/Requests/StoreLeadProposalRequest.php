<?php

namespace App\Modules\Lead\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Validates a direct-creation proposal for a lead.
 *
 * POST /api/v1/leads/{lead}/proposals
 *
 * Accepts the EXACT same field shape as the pipeline's stage_results entry
 * for type=proposal, so the frontend can reuse the same ProposalDirectUploadTab
 * form and payload mapper without any changes.
 */
class StoreLeadProposalRequest extends FormRequest
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
            'upload_file' => [
                Rule::requiredIf(fn () => $this->input('creation_type') === 'direct_upload' && $this->input('proposal_source') !== 'use_template'),
                'nullable',
                'file',
                'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg',
                'max:51200',
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', 'max:50'],

            // Proposal-specific
            'proposal_source' => ['nullable', 'string', 'in:upload_new,use_template'],
            'template_id' => ['nullable', 'integer', 'min:1'],
            'validity_date' => ['nullable', 'date'],
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
