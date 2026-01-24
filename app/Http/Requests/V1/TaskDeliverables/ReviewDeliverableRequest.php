<?php

namespace App\Http\Requests\V1\TaskDeliverables;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReviewDeliverableRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in service
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isApproval = $this->routeIs('*.approve');
        
        return [
            'pointsAwarded' => $isApproval ? ['integer', 'min:1', 'max:10'] : ['integer', 'min:1', 'max:5'],
            'pointsDeducted' => !$isApproval ? ['integer', 'min:1', 'max:5'] : ['integer', 'min:1', 'max:5'],
            'reviewNotes' => $isApproval ? ['nullable', 'string', 'max:1000'] : ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'pointsAwarded.integer' => 'Points awarded must be a valid integer',
            'pointsAwarded.min' => 'Points awarded must be at least 1',
            'pointsAwarded.max' => 'Points awarded cannot exceed 10',
            'pointsDeducted.integer' => 'Points deducted must be a valid integer',
            'pointsDeducted.min' => 'Points deducted must be at least 1',
            'pointsDeducted.max' => 'Points deducted cannot exceed 5',
            'reviewNotes.required' => 'Review notes are required when rejecting a deliverable',
            'reviewNotes.max' => 'Review notes cannot exceed 1000 characters',
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