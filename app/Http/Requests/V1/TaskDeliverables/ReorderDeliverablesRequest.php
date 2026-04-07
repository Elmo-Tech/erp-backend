<?php

namespace App\Http\Requests\V1\TaskDeliverables;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReorderDeliverablesRequest extends FormRequest
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
            'deliverableIds' => ['required', 'array', 'min:1'],
            'deliverableIds.*' => ['integer', 'exists:task_deliverables,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'deliverableIds.required' => 'Deliverable IDs are required',
            'deliverableIds.array' => 'Deliverable IDs must be an array',
            'deliverableIds.min' => 'At least one deliverable ID is required',
            'deliverableIds.*.integer' => 'Each deliverable ID must be a valid integer',
            'deliverableIds.*.exists' => 'One or more deliverable IDs do not exist',
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
