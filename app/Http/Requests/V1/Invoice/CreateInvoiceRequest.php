<?php

namespace App\Http\Requests\V1\Invoice;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'projectId' => 'required|integer|exists:projects,id',
            'contractId' => 'nullable|integer|exists:contracts,id',
            'milestoneName' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0|max:999999999.99',
            'currency' => 'required|string|size:3|in:USD,EUR,SAR,AED',
            'dueDate' => 'required|date|after:today',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'projectId.required' => __('validation.required', ['attribute' => 'projectId']),
            'projectId.exists' => __('validation.exists', ['attribute' => 'projectId']),
            'contractId.exists' => __('validation.exists', ['attribute' => 'contractId']),
            'amount.required' => __('validation.required', ['attribute' => 'amount']),
            'amount.numeric' => __('validation.numeric', ['attribute' => 'amount']),
            'amount.min' => __('validation.min.numeric', ['attribute' => 'amount', 'min' => 0]),
            'currency.required' => __('validation.required', ['attribute' => 'currency']),
            'currency.in' => __('validation.in', ['attribute' => 'currency']),
            'dueDate.required' => __('validation.required', ['attribute' => 'dueDate']),
            'dueDate.after' => __('validation.after', ['attribute' => 'dueDate', 'date' => 'today']),
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(
                __('validation.validation_failed'),
                422,
                $validator->errors()->toArray()
            )
        );
    }
}