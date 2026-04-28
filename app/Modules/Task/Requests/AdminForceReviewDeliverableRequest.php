<?php

namespace App\Modules\Task\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AdminForceReviewDeliverableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    public function rules(): array
    {
        return [
            'pointsAwarded' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'reviewNotes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'pointsAwarded.integer' => __('validation.integer', ['attribute' => 'pointsAwarded']),
            'pointsAwarded.min' => __('validation.min.numeric', ['attribute' => 'pointsAwarded', 'min' => 0]),
            'pointsAwarded.max' => __('validation.max.numeric', ['attribute' => 'pointsAwarded', 'max' => 1000]),
            'reviewNotes.string' => __('validation.string', ['attribute' => 'reviewNotes']),
            'reviewNotes.max' => __('validation.max.string', ['attribute' => 'reviewNotes', 'max' => 5000]),
        ];
    }

    public function attributes(): array
    {
        return [
            'pointsAwarded' => __('attributes.points_awarded'),
            'reviewNotes' => __('attributes.review_notes'),
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
