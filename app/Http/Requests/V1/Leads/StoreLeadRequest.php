<?php

namespace App\Http\Requests\V1\Leads;

use App\Enums\LeadPriority;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\ReferenceType;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'company' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', Rule::enum(LeadSource::class)],
            'referenceType' => ['nullable', Rule::enum(ReferenceType::class)],
            'referenceId' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', Rule::enum(LeadStatus::class)],
            'priority' => ['nullable', Rule::enum(LeadPriority::class)],
            'expectedBudget' => ['nullable', 'numeric', 'min:0'],
            'expectedCloseDate' => ['nullable', 'date'],
            'nextFollowUpAt' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('validation.required', ['attribute' => 'name']),
            'email.required' => __('validation.required', ['attribute' => 'email']),
            'email.email' => __('validation.email', ['attribute' => 'email']),
            'referenceId.exists' => __('validation.exists', ['attribute' => 'referenceId']),
            'expectedBudget.numeric' => __('validation.numeric', ['attribute' => 'expectedBudget']),
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                __('leads.validation_failed')
            )
        );
    }
}