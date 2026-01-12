<?php

namespace App\Http\Requests\V1\Proposals;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'leadId' => ['required', 'integer', 'exists:leads,id'],
            'templateId' => ['nullable', 'integer', 'exists:proposal_templates,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'expiryDate' => ['nullable', 'date', 'after:today'],
            'internalNotes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'leadId.required' => __('validation.required', ['attribute' => 'leadId']),
            'leadId.exists' => __('validation.exists', ['attribute' => 'leadId']),
            'title.required' => __('validation.required', ['attribute' => 'title']),
            'expiryDate.after' => __('validation.after', ['attribute' => 'expiryDate', 'date' => 'today']),
        ];
    }

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
