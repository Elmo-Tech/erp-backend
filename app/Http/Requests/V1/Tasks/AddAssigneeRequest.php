<?php

namespace App\Http\Requests\V1\Tasks;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AddAssigneeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'userId' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', 'string', 'in:developer,reviewer,lead'],
            'isPrimary' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'userId.required' => __('validation.required', ['attribute' => 'userId']),
            'userId.exists' => __('validation.exists', ['attribute' => 'userId']),
            'role.required' => __('validation.required', ['attribute' => 'role']),
            'role.in' => __('validation.in', ['attribute' => 'role']),
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(
                __('validation.failed'),
                422,
                $validator->errors()->toArray()
            )
        );
    }
}
