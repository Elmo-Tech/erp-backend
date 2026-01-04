<?php

namespace App\Http\Requests\V1\Contracts;

use App\Enums\MilestoneTriggerType;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'templateId' => ['nullable', 'integer', 'exists:contract_templates,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            
            'partyOne' => ['nullable', 'array'],
            'partyOne.company' => ['nullable', 'string', 'max:255'],
            'partyOne.representative' => ['nullable', 'string', 'max:255'],
            'partyOne.title' => ['nullable', 'string', 'max:100'],
            'partyOne.address' => ['nullable', 'string'],
            
            'partyTwo' => ['nullable', 'array'],
            'partyTwo.company' => ['nullable', 'string', 'max:255'],
            'partyTwo.name' => ['nullable', 'string', 'max:255'],
            'partyTwo.representative' => ['nullable', 'string', 'max:255'],
            'partyTwo.title' => ['nullable', 'string', 'max:100'],
            'partyTwo.address' => ['nullable', 'string'],
            
            'totalAmount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'paymentTerms' => ['nullable', 'string'],
            
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date', 'after:startDate'],
            
            'milestones' => ['nullable', 'array'],
            'milestones.*.title' => ['required_with:milestones', 'string', 'max:255'],
            'milestones.*.description' => ['nullable', 'string'],
            'milestones.*.percentage' => ['required_with:milestones', 'numeric', 'min:0', 'max:100'],
            'milestones.*.dueDate' => ['nullable', 'date'],
            'milestones.*.triggerType' => ['nullable', Rule::enum(MilestoneTriggerType::class)],
            'milestones.*.triggerValue' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                __('contracts.validation_failed')
            )
        );
    }
}
