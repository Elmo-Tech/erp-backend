<?php

namespace App\Http\Requests\V1\Tasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Helpers\ApiResponse;
use Illuminate\Validation\Rule;

class ReviewTaskDeliverableRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware and policies
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'reviewNotes' => ['nullable', 'string', 'max:2000'],
            'pointsAwarded' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.required' => __('validation.required', ['attribute' => 'status']),
            'status.in' => __('validation.in', ['attribute' => 'status']),
            'reviewNotes.string' => __('validation.string', ['attribute' => 'reviewNotes']),
            'reviewNotes.max' => __('validation.max.string', ['attribute' => 'reviewNotes', 'max' => 2000]),
            'pointsAwarded.integer' => __('validation.integer', ['attribute' => 'pointsAwarded']),
            'pointsAwarded.min' => __('validation.min.numeric', ['attribute' => 'pointsAwarded', 'min' => 0]),
            'pointsAwarded.max' => __('validation.max.numeric', ['attribute' => 'pointsAwarded', 'max' => 1000]),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'status' => __('attributes.status'),
            'reviewNotes' => __('attributes.review_notes'),
            'pointsAwarded' => __('attributes.points_awarded'),
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
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