<?php

namespace App\Http\Requests\V1\TaskDeliverables;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreTaskDeliverableRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'acceptanceCriteria' => ['nullable', 'string', 'max:2000'],
            'isBonus' => ['boolean'],
            'order' => ['integer', 'min:1'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Deliverable title is required',
            'title.max' => 'Deliverable title cannot exceed 255 characters',
            'description.max' => 'Description cannot exceed 1000 characters',
            'acceptanceCriteria.max' => 'Acceptance criteria cannot exceed 2000 characters',
            'order.integer' => 'Order must be a valid integer',
            'order.min' => 'Order must be at least 1',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(
                'Validation failed',
                $validator->errors()->toArray(),
                422
            )
        );
    }
}
