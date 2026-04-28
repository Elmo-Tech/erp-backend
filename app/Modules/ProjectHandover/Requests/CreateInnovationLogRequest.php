<?php

namespace App\Modules\ProjectHandover\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateInnovationLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'projectId' => 'sometimes|nullable|integer|exists:projects,id',
            'taskId' => 'sometimes|nullable|integer|exists:tasks,id',
            'toolName' => 'required|string|max:255',
            'category' => 'required|string|in:ai_tool,automation,new_tech,process_improvement',
            'description' => 'required|string|max:2000',
            'impactDescription' => 'sometimes|nullable|string|max:2000',
            'impactLevel' => 'sometimes|string|in:low,medium,high',
            'timeSavedMinutes' => 'sometimes|nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'toolName.required' => __('validation.required', ['attribute' => 'toolName']),
            'category.required' => __('validation.required', ['attribute' => 'category']),
            'category.in' => __('validation.in', ['attribute' => 'category']),
            'description.required' => __('validation.required', ['attribute' => 'description']),
            'impactLevel.in' => __('validation.in', ['attribute' => 'impactLevel']),
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(__('messages.validation_error'), 422, $validator->errors()->toArray())
        );
    }
}
