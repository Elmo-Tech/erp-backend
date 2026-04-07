<?php

namespace App\Http\Requests\V1\Tasks;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateTaskDeliverableRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'acceptanceCriteria' => ['nullable', 'string', 'max:2000'],
            'isBonus' => ['nullable', 'boolean'],
            'order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => __('validation.required', ['attribute' => 'title']),
            'title.string' => __('validation.string', ['attribute' => 'title']),
            'title.max' => __('validation.max.string', ['attribute' => 'title', 'max' => 255]),
            'description.string' => __('validation.string', ['attribute' => 'description']),
            'description.max' => __('validation.max.string', ['attribute' => 'description', 'max' => 2000]),
            'acceptanceCriteria.string' => __('validation.string', ['attribute' => 'acceptanceCriteria']),
            'acceptanceCriteria.max' => __('validation.max.string', ['attribute' => 'acceptanceCriteria', 'max' => 2000]),
            'isBonus.boolean' => __('validation.boolean', ['attribute' => 'isBonus']),
            'order.integer' => __('validation.integer', ['attribute' => 'order']),
            'order.min' => __('validation.min.numeric', ['attribute' => 'order', 'min' => 0]),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'title' => __('attributes.title'),
            'description' => __('attributes.description'),
            'acceptanceCriteria' => __('attributes.acceptance_criteria'),
            'isBonus' => __('attributes.is_bonus'),
            'order' => __('attributes.order'),
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
