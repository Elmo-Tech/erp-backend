<?php

namespace App\Http\Requests\V1\Activity;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateActivityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'assignedTo' => ['sometimes', 'integer', 'exists:users,id'],
            'scheduledAt' => ['nullable', 'date'],
            'outcomeSummary' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.max' => __('validation.max.string', ['attribute' => 'title', 'max' => 255]),
            'description.max' => __('validation.max.string', ['attribute' => 'description', 'max' => 1000]),
            'assignedTo.exists' => __('validation.exists', ['attribute' => 'assignedTo']),
            'scheduledAt.date' => __('validation.date', ['attribute' => 'scheduledAt']),
            'outcomeSummary.max' => __('validation.max.string', ['attribute' => 'outcomeSummary', 'max' => 1000]),
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(
                __('validation.failed'),
                $validator->errors()->toArray(),
                422
            )
        );
    }
}