<?php

namespace App\Modules\Performance\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateEvaluationPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'startDate' => 'required|date|after_or_equal:today',
            'endDate' => 'required|date|after:startDate',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('validation.required', ['attribute' => 'name']),
            'startDate.required' => __('validation.required', ['attribute' => 'startDate']),
            'startDate.after_or_equal' => __('validation.after_or_equal', ['attribute' => 'startDate', 'date' => 'today']),
            'endDate.required' => __('validation.required', ['attribute' => 'endDate']),
            'endDate.after' => __('validation.after', ['attribute' => 'endDate', 'date' => 'startDate']),
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(__('messages.validation_error'), 422, $validator->errors()->toArray())
        );
    }
}
