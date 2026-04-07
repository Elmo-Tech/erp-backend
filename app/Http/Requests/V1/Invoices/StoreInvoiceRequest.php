<?php

namespace App\Http\Requests\V1\Invoices;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreInvoiceRequest extends FormRequest
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
            'projectId' => ['required', 'integer', 'exists:projects,id'],
            'contractId' => ['nullable', 'integer', 'exists:contracts,id'],
            'milestoneName' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'dueDate' => ['required', 'date', 'after:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
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
            'projectId.required' => 'The projectId field is required.',
            'projectId.integer' => 'The projectId must be an integer.',
            'projectId.exists' => 'The selected projectId does not exist.',
            'contractId.integer' => 'The contractId must be an integer.',
            'contractId.exists' => 'The selected contractId does not exist.',
            'milestoneName.string' => 'The milestoneName must be a string.',
            'milestoneName.max' => 'The milestoneName may not be greater than 255 characters.',
            'amount.required' => 'The amount field is required.',
            'amount.numeric' => 'The amount must be a valid number.',
            'amount.min' => 'The amount must be greater than 0.',
            'currency.required' => 'The currency field is required.',
            'currency.string' => 'The currency must be a string.',
            'currency.size' => 'The currency must be exactly 3 characters.',
            'currency.regex' => 'The currency must be a valid 3-letter uppercase currency code (e.g., USD, EUR).',
            'dueDate.required' => 'The dueDate field is required.',
            'dueDate.date' => 'The dueDate must be a valid date.',
            'dueDate.after' => 'The dueDate must be a future date.',
            'notes.string' => 'The notes must be a string.',
            'notes.max' => 'The notes may not be greater than 1000 characters.',
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
                'Validation failed for invoice creation'
            )
        );
    }
}
