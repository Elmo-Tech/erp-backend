<?php

namespace App\Modules\Performance\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bugSeverityScore' => 'sometimes|numeric|min:0|max:15',
            'codeQualityScore' => 'sometimes|numeric|min:0|max:15',
            'innovationScore' => 'sometimes|numeric|min:0|max:20',
            'onTimeDeliveryScore' => 'sometimes|numeric|min:0|max:15',
            'flexibilityScore' => 'sometimes|numeric|min:0|max:10',
            'communicationScore' => 'sometimes|numeric|min:0|max:10',
            'attendanceScore' => 'sometimes|numeric|min:0|max:10',
            'initiativeScore' => 'sometimes|numeric|min:0|max:5',
            'evaluatorNotes' => 'sometimes|nullable|string|max:2000',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(__('messages.validation_error'), 422, $validator->errors()->toArray())
        );
    }
}
