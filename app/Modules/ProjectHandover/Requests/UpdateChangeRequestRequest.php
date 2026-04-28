<?php

namespace App\Modules\ProjectHandover\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateChangeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'impactAnalysis' => ['nullable', 'string'],
            'isBillable' => ['nullable', 'boolean'],
            'estimatedHours' => ['nullable', 'numeric', 'min:0'],
            'estimatedCost' => ['nullable', 'numeric', 'min:0'],
            'additionalDays' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                __('change_requests.validation_failed')
            )
        );
    }
}
