<?php

namespace App\Modules\Task\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class AdminChangeDeliverableStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',
                'required',
                Rule::in(['pending', 'submitted', 'under_review', 'approved', 'rejected']),
            ],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => __('validation.required', ['attribute' => 'status']),
            'status.in' => __('validation.in', ['attribute' => 'status']),
            'reason.string' => __('validation.string', ['attribute' => 'reason']),
            'reason.max' => __('validation.max.string', ['attribute' => 'reason', 'max' => 2000]),
        ];
    }

    public function attributes(): array
    {
        return [
            'status' => __('attributes.status'),
            'reason' => __('attributes.reason'),
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(
                __('messages.validation_failed'),
                422,
                $validator->errors()->toArray()
            )
        );
    }
}
