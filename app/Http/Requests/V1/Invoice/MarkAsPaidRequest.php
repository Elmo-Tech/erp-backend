<?php

namespace App\Http\Requests\V1\Invoice;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class MarkAsPaidRequest extends FormRequest
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
            'paymentMethod' => 'nullable|string|max:100',
            'transactionId' => 'nullable|string|max:255',
            'paymentNotes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'paymentMethod.string' => __('validation.string', ['attribute' => 'paymentMethod']),
            'transactionId.string' => __('validation.string', ['attribute' => 'transactionId']),
            'paymentNotes.string' => __('validation.string', ['attribute' => 'paymentNotes']),
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