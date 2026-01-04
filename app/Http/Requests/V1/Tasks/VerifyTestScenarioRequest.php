<?php

namespace App\Http\Requests\V1\Tasks;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class VerifyTestScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'passed' => ['required', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'passed.required' => __('validation.required', ['attribute' => 'passed']),
            'passed.boolean' => __('validation.boolean', ['attribute' => 'passed']),
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
