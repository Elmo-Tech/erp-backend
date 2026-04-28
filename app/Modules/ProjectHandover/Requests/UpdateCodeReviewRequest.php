<?php

namespace App\Modules\ProjectHandover\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateCodeReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codeQualityScore' => 'sometimes|integer|min:1|max:5',
            'readabilityScore' => 'sometimes|integer|min:1|max:5',
            'scalabilityScore' => 'sometimes|integer|min:1|max:5',
            'testingScore' => 'sometimes|integer|min:1|max:5',
            'documentationScore' => 'sometimes|integer|min:1|max:5',
            'reviewNotes' => 'sometimes|nullable|string|max:2000',
            'improvementSuggestions' => 'sometimes|nullable|string|max:2000',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(__('messages.validation_error'), 422, $validator->errors()->toArray())
        );
    }
}
