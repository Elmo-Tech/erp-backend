<?php

namespace App\Modules\Activity\Requests;

use App\Enums\ActivityType;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateActivityRequest extends FormRequest
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
            'subjectType' => ['required', 'string', 'in:App\\Models\\Lead,App\\Models\\Project'],
            'subjectId' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'string', Rule::enum(ActivityType::class)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'assignedTo' => ['nullable', 'integer', 'exists:users,id'],
            'scheduledAt' => ['nullable', 'date', 'after:now'],
            'outcomeSummary' => ['nullable', 'string', 'max:1000'],
            'isCompleted' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'subjectType.required' => __('validation.required', ['attribute' => 'subjectType']),
            'subjectType.in' => __('validation.in', ['attribute' => 'subjectType']),
            'subjectId.required' => __('validation.required', ['attribute' => 'subjectId']),
            'subjectId.integer' => __('validation.integer', ['attribute' => 'subjectId']),
            'type.required' => __('validation.required', ['attribute' => 'type']),
            'type.enum' => __('validation.in', ['attribute' => 'type']),
            'title.required' => __('validation.required', ['attribute' => 'title']),
            'title.max' => __('validation.max.string', ['attribute' => 'title', 'max' => 255]),
            'description.max' => __('validation.max.string', ['attribute' => 'description', 'max' => 1000]),
            'assignedTo.exists' => __('validation.exists', ['attribute' => 'assignedTo']),
            'scheduledAt.date' => __('validation.date', ['attribute' => 'scheduledAt']),
            'scheduledAt.after' => __('validation.after', ['attribute' => 'scheduledAt', 'date' => 'now']),
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
