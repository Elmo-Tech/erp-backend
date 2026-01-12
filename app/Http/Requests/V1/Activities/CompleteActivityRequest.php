<?php

namespace App\Http\Requests\V1\Activities;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CompleteActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', 'string', 'max:5000'],
            'nextAction' => ['nullable', 'string', 'max:500'],
            'reminderAt' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'outcome.required' => __('activities.outcome_required'),
            'outcome.max' => __('activities.outcome_max'),
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                __('activities.validation_failed')
            )
        );
    }
}
