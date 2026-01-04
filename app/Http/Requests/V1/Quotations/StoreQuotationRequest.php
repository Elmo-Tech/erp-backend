<?php

namespace App\Http\Requests\V1\Quotations;

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
            'leadId' => ['required', 'integer', 'exists:leads,id'],
            'templateId' => ['nullable', 'integer', 'exists:proposal_templates,id'],
            
            // Items-based pricing (new)
            'items' => ['required_without:estimations', 'array'],
            'items.*.resourceType' => ['required_with:items', Rule::enum(ResourceType::class)],
            'items.*.description' => ['required_with:items', 'string', 'max:255'],
            'items.*.estimatedHours' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.hourlyRate' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.internalHourlyCost' => ['nullable', 'numeric', 'min:0'],
            
            // Legacy estimations support
            'estimations' => ['required_without:items', 'array'],
            'estimations.*' => ['required_with:estimations', 'numeric', 'min:0'],
            'hourlyRate' => ['required_with:estimations', 'numeric', 'gt:0'],
            
            // Pricing
            'discountPercentage' => ['nullable', 'numeric', 'between:0,100'],
            'currency' => ['required', 'string', 'size:3'],
            
            // Commission
            'commissionType' => ['nullable', Rule::enum(CommissionType::class)],
            'commissionPercentage' => ['nullable', 'numeric', 'between:0,100'],
            'commissionUserId' => ['nullable', 'integer', 'exists:users,id'],
            
            // Override
            'overridePrice' => ['nullable', 'numeric', 'min:0'],
            'overrideReason' => ['nullable', 'required_with:overridePrice', 'string'],
            
            // Dates
            'expiryDate' => ['nullable', 'date', 'after:today'],
            
            // Notes
            'notes' => ['nullable', 'string'],
            'termsConditions' => ['nullable', 'string'],
            
            'status' => ['nullable', Rule::enum(QuotationStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'leadId.required' => __('validation.required', ['attribute' => 'leadId']),
            'leadId.exists' => __('validation.exists', ['attribute' => 'leadId']),
            'items.required_without' => __('quotations.items_or_estimations_required'),
            'items.*.resourceType.required_with' => __('validation.required', ['attribute' => 'resourceType']),
            'items.*.estimatedHours.required_with' => __('validation.required', ['attribute' => 'estimatedHours']),
            'overrideReason.required_with' => __('quotations.override_reason_required'),
            'expiryDate.after' => __('quotations.expiry_must_be_future'),
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