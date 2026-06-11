<?php

namespace App\Modules\Settings\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateInternalCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_internal_costs') ?? false;
    }

    public function rules(): array
    {
        return [
            'internalHourlyCost' => [
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
            'internalHourlyCost.required' => 'The internal hourly cost is required',
            'internalHourlyCost.numeric' => 'The internal hourly cost must be a number',
            'internalHourlyCost.min' => 'The internal hourly cost must be at least 0.01',
            'internalHourlyCost.max' => 'The internal hourly cost cannot exceed 9999.99',
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
