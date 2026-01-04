<?php

namespace App\Http\Requests\V1\Contracts;

use App\Enums\MilestoneTriggerType;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quotationId' => ['nullable', 'integer', 'exists:quotations,id'],
            'projectId' => ['nullable', 'integer', 'exists:projects,id'],
            'templateId' => ['nullable', 'integer', 'exists:contract_templates,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            
            // Parties
            'partyOne' => ['required', 'array'],
            'partyOne.company' => ['required', 'string', 'max:255'],
            'partyOne.representative' => ['required', 'string', 'max:255'],
            'partyOne.title' => ['nullable', 'string', 'max:100'],
            'partyOne.address' => ['nullable', 'string'],
            
            'partyTwo' => ['required', 'array'],
            'partyTwo.company' => ['nullable', 'string', 'max:255'],
            'partyTwo.name' => ['nullable', 'string', 'max:255'],
            'partyTwo.representative' => ['required', 'string', 'max:255'],
            'partyTwo.title' => ['nullable', 'string', 'max:100'],
            'partyTwo.address' => ['nullable', 'string'],
            
            // Financial
            'totalAmount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'paymentTerms' => ['nullable', 'string'],
            
            // Dates
            'startDate' => ['required', 'date'],
            'endDate' => ['nullable', 'date', 'after:startDate'],
            
            // Milestones
            'milestones' => ['nullable', 'array'],
            'milestones.*.title' => ['required_with:milestones', 'string', 'max:255'],
            'milestones.*.description' => ['nullable', 'string'],
            'milestones.*.percentage' => ['required_with:milestones', 'numeric', 'min:0', 'max:100'],
            'milestones.*.dueDate' => ['nullable', 'date'],
            'milestones.*.triggerType' => ['nullable', Rule::enum(MilestoneTriggerType::class)],
            'milestones.*.triggerValue' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $milestones = $this->input('milestones', []);
            if (!empty($milestones)) {
                $totalPercentage = array_sum(array_column($milestones, 'percentage'));
                if ($totalPercentage != 100) {
                    $validator->errors()->add('milestones', __('contracts.milestones_must_equal_100'));
                }
            }
        });
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
