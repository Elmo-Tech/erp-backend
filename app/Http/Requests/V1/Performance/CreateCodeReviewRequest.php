<?php

namespace App\Http\Requests\V1\Performance;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateCodeReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'taskId' => 'required|integer|exists:tasks,id',
            'developerId' => 'required|integer|exists:users,id',
            'codeQualityScore' => 'sometimes|integer|min:1|max:5',
            'readabilityScore' => 'sometimes|integer|min:1|max:5',
            'scalabilityScore' => 'sometimes|integer|min:1|max:5',
            'testingScore' => 'sometimes|integer|min:1|max:5',
            'documentationScore' => 'sometimes|integer|min:1|max:5',
            'reviewNotes' => 'sometimes|nullable|string|max:2000',
            'improvementSuggestions' => 'sometimes|nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'taskId.required' => __('validation.required', ['attribute' => 'taskId']),
            'taskId.exists' => __('validation.exists', ['attribute' => 'taskId']),
            'developerId.required' => __('validation.required', ['attribute' => 'developerId']),
            'developerId.exists' => __('validation.exists', ['attribute' => 'developerId']),
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(__('messages.validation_error'), 422, $validator->errors()->toArray())
        );
    }
}
