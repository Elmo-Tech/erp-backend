<?php

namespace App\Http\Requests\V1\Proposals;

use App\Enums\ProposalActionType;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreProposalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Three paths supported:
     * 1. Skip path: Mark proposal as skipped with reason (min 30 chars)
     * 2. Schedule path: Create proposal with follow-up task (requires assigned_to, due_date)
     * 3. Upload path: Create proposal with file upload
     *
     * expiryDays is required for all paths except 'skip'
     */
    public function rules(): array
    {
        $baseRules = [
            'leadId' => ['required', 'integer', 'exists:leads,id'],
            'templateId' => ['nullable', 'integer', 'exists:proposal_templates,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'expiryDate' => ['nullable', 'date', 'after:today'],
            'expiryDays' => ['nullable', 'integer', 'min:1'], // Required for all paths except 'skip'
            'internalNotes' => ['nullable', 'string'],

            // Proposal action type using enum
            'actionType' => ['nullable', 'string', Rule::in(ProposalActionType::values())],

            // Skip path validation
            'skipReason' => ['nullable', 'string', 'min:30'],

            // Schedule path validation (for creating follow-up task)
            'taskAssignedTo' => ['nullable', 'integer', 'exists:users,id'],
            'taskDueDate' => ['nullable', 'date', 'after:today'],
            'taskTitle' => ['nullable', 'string', 'max:255'],

            // Upload path validation
            'file' => ['nullable', 'file', 'mimes:pdf,ppt,pptx', 'max:20480'],
        ];

        return $baseRules;
    }

    /**
     * Configure the validator instance.
     *
     * Validates conditional rules based on actionType:
     * - If actionType is 'skip': skip_reason must be required with min 30 chars, expiryDays NOT required
     * - If actionType is 'schedule': taskAssignedTo and taskDueDate are required
     * - If actionType is 'upload': file is required
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $actionType = $this->input('actionType', ProposalActionType::DRAFT->value);

            if ($actionType === ProposalActionType::SKIP->value) {
                $this->validateSkipPath($validator);
            } else {
                $this->validateExpiryDays($validator);

                if ($actionType === ProposalActionType::SCHEDULE->value) {
                    $this->validateSchedulePath($validator);
                }

                if ($actionType === ProposalActionType::UPLOAD->value) {
                    $this->validateUploadPath($validator);
                }
            }
        });
    }

    /**
     * Validate skip path requirements
     */
    private function validateSkipPath(Validator $validator): void
    {
        if (! $this->input('skipReason')) {
            $validator->errors()->add(
                'skipReason',
                __('validation.required', ['attribute' => 'skipReason'])
            );
        } elseif (strlen($this->input('skipReason')) < 30) {
            $validator->errors()->add(
                'skipReason',
                __('validation.min.string', ['attribute' => 'skipReason', 'min' => 30])
            );
        }
    }

    /**
     * Validate expiry days for non-skip paths
     */
    private function validateExpiryDays(Validator $validator): void
    {
        if ($this->input('expiryDays') && $this->input('expiryDays') < 1) {
            $validator->errors()->add(
                'expiryDays',
                __('validation.min.numeric', ['attribute' => 'expiryDays', 'min' => 1])
            );
        }
    }

    /**
     * Validate schedule path requirements
     */
    private function validateSchedulePath(Validator $validator): void
    {
        if (! $this->input('taskAssignedTo')) {
            $validator->errors()->add(
                'taskAssignedTo',
                __('validation.required', ['attribute' => 'taskAssignedTo'])
            );
        }
        if (! $this->input('taskDueDate')) {
            $validator->errors()->add(
                'taskDueDate',
                __('validation.required', ['attribute' => 'taskDueDate'])
            );
        }
    }

    /**
     * Validate upload path requirements
     */
    private function validateUploadPath(Validator $validator): void
    {
        if (! $this->hasFile('file')) {
            $validator->errors()->add(
                'file',
                __('validation.required', ['attribute' => 'file'])
            );
        }
    }

    /**
     * custom messages for validator * Get errors.
     */
    public function messages(): array
    {
        return [
            'leadId.required' => __('validation.required', ['attribute' => 'leadId']),
            'leadId.exists' => __('validation.exists', ['attribute' => 'leadId']),
            'title.required' => __('validation.required', ['attribute' => 'title']),
            'expiryDate.after' => __('validation.after', ['attribute' => 'expiryDate', 'date' => 'today']),
            'expiryDays.integer' => __('validation.integer', ['attribute' => 'expiryDays']),
            'expiryDays.min' => __('validation.min.numeric', ['attribute' => 'expiryDays', 'min' => 1]),
            'taskDueDate.after' => __('validation.after', ['attribute' => 'taskDueDate', 'date' => 'today']),
            'skipReason.min' => __('validation.min.string', ['attribute' => 'skipReason', 'min' => 30]),
            'actionType.in' => __('validation.in', ['attribute' => 'actionType']),
            'file.mimes' => __('validation.mimes', ['attribute' => 'file', 'values' => 'pdf, ppt, pptx']),
            'file.max' => __('validation.max.file', ['attribute' => 'file', 'max' => '20MB']),
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                __('proposals.validation_failed')
            )
        );
    }
}
