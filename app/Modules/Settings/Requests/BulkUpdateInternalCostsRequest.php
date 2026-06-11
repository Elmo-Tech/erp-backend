<?php

namespace App\Modules\Settings\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class BulkUpdateInternalCostsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_internal_costs') ?? false;
    }

    public function rules(): array
    {
        return [
            'users' => 'required|array|min:1|max:100',
            'users.*.userId' => 'required|integer|exists:users,id',
            'users.*.internalHourlyCost' => [
                'required',
                'numeric',
                'min:0.01',
                'max:9999.99',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'users.required' => 'The users array is required',
            'users.array' => 'The users must be an array',
            'users.min' => 'At least one user must be provided',
            'users.max' => 'Cannot update more than 100 users at once',
            'users.*.userId.required' => 'User ID is required for each user',
            'users.*.userId.integer' => 'User ID must be an integer',
            'users.*.userId.exists' => 'User does not exist',
            'users.*.internalHourlyCost.required' => 'Internal hourly cost is required for each user',
            'users.*.internalHourlyCost.numeric' => 'Internal hourly cost must be a number',
            'users.*.internalHourlyCost.min' => 'Internal hourly cost must be at least 0.01',
            'users.*.internalHourlyCost.max' => 'Internal hourly cost cannot exceed 9999.99',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError($validator->errors()->toArray(), 'Validation failed')
        );
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            ApiResponse::forbidden('You do not have permission to manage internal costs')
        );
    }
}
