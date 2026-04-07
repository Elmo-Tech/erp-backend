<?php

namespace App\Http\Requests\V1\Admin;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AdminUpdateDeliverableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'acceptanceCriteria' => ['nullable', 'string', 'max:5000'],
            'submissionNotes' => ['nullable', 'string', 'max:5000'],
            'reviewNotes' => ['nullable', 'string', 'max:5000'],
            'pointsAwarded' => ['nullable', 'integer', 'min:-1000', 'max:1000'],
            'isBonus' => ['nullable', 'boolean'],
            'order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.string' => __('validation.string', ['attribute' => 'title']),
            'title.max' => __('validation.max.string', ['attribute' => 'title', 'max' => 255]),

            'description.string' => __('validation.string', ['attribute' => 'description']),
            'description.max' => __('validation.max.string', ['attribute' => 'description', 'max' => 5000]),

            'acceptanceCriteria.string' => __('validation.string', ['attribute' => 'acceptanceCriteria']),
            'acceptanceCriteria.max' => __('validation.max.string', ['attribute' => 'acceptanceCriteria', 'max' => 5000]),

            'submissionNotes.string' => __('validation.string', ['attribute' => 'submissionNotes']),
            'submissionNotes.max' => __('validation.max.string', ['attribute' => 'submissionNotes', 'max' => 5000]),

            'reviewNotes.string' => __('validation.string', ['attribute' => 'reviewNotes']),
            'reviewNotes.max' => __('validation.max.string', ['attribute' => 'reviewNotes', 'max' => 5000]),

            'pointsAwarded.integer' => __('validation.integer', ['attribute' => 'pointsAwarded']),
            'pointsAwarded.min' => __('validation.min.numeric', ['attribute' => 'pointsAwarded', 'min' => -1000]),
            'pointsAwarded.max' => __('validation.max.numeric', ['attribute' => 'pointsAwarded', 'max' => 1000]),

            'isBonus.boolean' => __('validation.boolean', ['attribute' => 'isBonus']),

            'order.integer' => __('validation.integer', ['attribute' => 'order']),
            'order.min' => __('validation.min.numeric', ['attribute' => 'order', 'min' => 0]),
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => __('attributes.title'),
            'description' => __('attributes.description'),
            'acceptanceCriteria' => __('attributes.acceptance_criteria'),
            'submissionNotes' => __('attributes.submission_notes'),
            'reviewNotes' => __('attributes.review_notes'),
            'pointsAwarded' => __('attributes.points_awarded'),
            'isBonus' => __('attributes.is_bonus'),
            'order' => __('attributes.order'),
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
