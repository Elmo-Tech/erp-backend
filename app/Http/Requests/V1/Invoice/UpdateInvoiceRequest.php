<?php

namespace App\Http\Requests\V1\Invoice;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateInvoiceRequest extends FormRequest
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
            'amount' => 'sometimes|numeric|min:0|max:999999999.99',
            'dueDate' => 'sometimes|date|after:today',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'amount.numeric' => __('validation.numeric', ['attribute' => 'amount']),
            'amount.min' => __('validation.min.numeric', ['attribute' => 'amount', 'min' => 0]),
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