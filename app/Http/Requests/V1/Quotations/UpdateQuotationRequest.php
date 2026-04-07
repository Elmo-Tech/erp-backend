<?php

namespace App\Http\Requests\V1\Quotations;

use App\Enums\QuotationStatus;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateQuotationRequest extends FormRequest
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
            'leadId' => ['sometimes', 'integer', 'exists:leads,id'],
            'estimations' => ['sometimes', 'array'],
            'estimations.*' => ['required_with:estimations', 'numeric', 'min:0'],
            'hourlyRate' => ['sometimes', 'numeric', 'gt:0'],
            'discountPercentage' => ['sometimes', 'numeric', 'between:0,100'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'status' => ['sometimes', 'nullable', Rule::enum(QuotationStatus::class)],
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
            'leadId.integer' => 'The leadId must be an integer.',
            'leadId.exists' => 'The selected leadId does not exist.',
            'estimations.array' => 'The estimations must be an array.',
            'estimations.*.required_with' => 'Each estimation value is required when estimations are provided.',
            'estimations.*.numeric' => 'Each estimation value must be numeric.',
            'estimations.*.min' => 'Each estimation value must be at least 0.',
            'hourlyRate.numeric' => 'The hourlyRate must be numeric.',
            'hourlyRate.gt' => 'The hourlyRate must be greater than 0.',
            'discountPercentage.numeric' => 'The discountPercentage must be numeric.',
            'discountPercentage.between' => 'The discountPercentage must be between 0 and 100.',
            'currency.string' => 'The currency must be a string.',
            'currency.size' => 'The currency must be exactly 3 characters.',
            'status.enum' => 'The selected status is invalid. Valid values are: draft, sent, accepted, rejected, expired.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                'Validation failed for quotation update'
            )
        );
    }
}
