<?php

namespace App\Modules\Quotation\Requests;

use App\Enums\CommissionType;
use App\Enums\QuotationStatus;
use App\Enums\ResourceType;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Base fields
            'leadId' => ['required', 'integer', 'exists:leads,id'],
            'proposalId' => ['nullable', 'integer', 'exists:proposals,id'],
            'title' => ['required', 'string', 'max:255'],
            'expiryDays' => ['required', 'integer', 'min:1'],
            'basePrice' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],

            // Discount
            'discountType' => ['nullable', 'in:amount,percent'],
            'discountValue' => ['nullable', 'numeric', 'min:0'],

            // Commission
            'commissionType' => ['nullable', Rule::enum(CommissionType::class)],
            'commissionValue' => ['nullable', 'numeric', 'min:0'],
            'commissionCalculation' => ['nullable', 'in:add,deduct'],
            'commissionUserId' => ['nullable', 'integer', 'exists:users,id'],

            // Items-based pricing (alternative to basePrice)
            'items' => ['required_without:basePrice', 'array'],
            'items.*.resourceType' => ['required_with:items', Rule::enum(ResourceType::class)],
            'items.*.description' => ['required_with:items', 'string', 'max:255'],
            'items.*.estimatedHours' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.hourlyRate' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.internalHourlyCost' => ['nullable', 'numeric', 'min:0'],

            // Legacy estimations support
            'estimations' => ['required_without:basePrice', 'array'],
            'estimations.*' => ['required_with:estimations', 'numeric', 'min:0'],
            'hourlyRate' => ['required_with:estimations', 'numeric', 'gt:0'],

            // Override
            'overridePrice' => ['nullable', 'numeric', 'min:0'],
            'overrideReason' => ['nullable', 'required_with:overridePrice', 'string'],

            // Payment Milestones (required, sum must equal 100%)
            'milestones' => ['required', 'array', 'min:1'],
            'milestones.*.name' => ['required', 'string', 'max:255'],
            'milestones.*.percentage' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'milestones.*.dueDate' => ['nullable', 'date'],
            'milestones.*.description' => ['nullable', 'string'],

            // Task linkage
            'taskId' => ['nullable', 'integer', 'exists:tasks,id'],

            // Template
            'templateId' => ['nullable', 'integer', 'exists:proposal_templates,id'],

            // Notes
            'notes' => ['nullable', 'string'],
            'termsConditions' => ['nullable', 'string'],

            'status' => ['nullable', Rule::enum(QuotationStatus::class)],
        ];
    }

    /**
     * Custom validation to ensure milestones sum to exactly 100%
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $milestones = $this->input('milestones', []);

            if (! empty($milestones)) {
                $totalPercentage = collect($milestones)->sum('percentage');

                if (round($totalPercentage, 2) !== 100.00) {
                    $validator->errors()->add(
                        'milestones',
                        __('quotations.milestones_must_equal_100', ['total' => $totalPercentage])
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'leadId.required' => __('validation.required', ['attribute' => 'leadId']),
            'leadId.exists' => __('validation.exists', ['attribute' => 'leadId']),
            'title.required' => __('validation.required', ['attribute' => 'title']),
            'expiryDays.required' => __('validation.required', ['attribute' => 'expiryDays']),
            'expiryDays.min' => __('validation.min.numeric', ['attribute' => 'expiryDays', 'min' => 1]),
            'basePrice.required' => __('validation.required', ['attribute' => 'basePrice']),
            'basePrice.min' => __('validation.min.numeric', ['attribute' => 'basePrice', 'min' => 0]),
            'currency.required' => __('validation.required', ['attribute' => 'currency']),
            'milestones.required' => __('quotations.milestones_required'),
            'milestones.min' => __('quotations.at_least_one_milestone'),
            'milestones.*.name.required' => __('validation.required', ['attribute' => 'milestone name']),
            'milestones.*.percentage.required' => __('validation.required', ['attribute' => 'milestone percentage']),
            'milestones.*.percentage.min' => __('validation.min.numeric', ['attribute' => 'percentage', 'min' => 0.01]),
            'milestones.*.percentage.max' => __('validation.max.numeric', ['attribute' => 'percentage', 'max' => 100]),
            'items.required_without' => __('quotations.items_or_estimations_required'),
            'items.*.resourceType.required_with' => __('validation.required', ['attribute' => 'resourceType']),
            'items.*.estimatedHours.required_with' => __('validation.required', ['attribute' => 'estimatedHours']),
            'overrideReason.required_with' => __('quotations.override_reason_required'),
            'taskId.exists' => __('validation.exists', ['attribute' => 'taskId']),
            'templateId.exists' => __('validation.exists', ['attribute' => 'templateId']),
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                __('quotations.validation_failed')
            )
        );
    }
}
