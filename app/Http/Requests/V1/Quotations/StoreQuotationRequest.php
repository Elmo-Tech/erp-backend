<?php

namespace App\Http\Requests\V1\Quotations;

use App\Enums\QuotationStatus;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreQuotationRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'leadId' => ['required', 'integer', 'exists:leads,id'],
            'estimations' => ['required', 'array'],
            'estimations.*' => ['required', 'numeric', 'min:0'],
            'hourlyRate' => ['required', 'numeric', 'gt:0'],
            'discountPercentage' => ['required', 'numeric', 'between:0,100'],
            'currency' => ['required', 'string', 'size:3'],
            'status' => ['nullable', Rule::enum(QuotationStatus::class)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'leadId.required' => 'The leadId field is required.',
            'leadId.integer' => 'The leadId must be an integer.',
            'leadId.exists' => 'The selected leadId does not exist.',
            'estimations.required' => 'The estimations field is required.',
            'estimations.array' => 'The estimations must be an array.',
            'estimations.*.required' => 'Each estimation value is required.',
            'estimations.*.numeric' => 'Each estimation value must be numeric.',
            'estimations.*.min' => 'Each estimation value must be at least 0.',
            'hourlyRate.required' => 'The hourlyRate field is required.',
            'hourlyRate.numeric' => 'The hourlyRate must be numeric.',
            'hourlyRate.gt' => 'The hourlyRate must be greater than 0.',
            'discountPercentage.required' => 'The discountPercentage field is required.',
            'discountPercentage.numeric' => 'The discountPercentage must be numeric.',
            'discountPercentage.between' => 'The discountPercentage must be between 0 and 100.',
            'currency.required' => 'The currency field is required.',
            'currency.string' => 'The currency must be a string.',
            'currency.size' => 'The currency must be exactly 3 characters.',
            'status.enum' => 'The selected status is invalid. Valid values are: draft, sent, accepted, rejected, expired.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                'Validation failed for quotation creation'
            )
        );
    }
}