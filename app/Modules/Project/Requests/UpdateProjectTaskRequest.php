<?php

namespace App\Modules\Project\Requests;

use App\Helpers\ApiResponse;
use App\Modules\Project\Enums\ExecutionTaskStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Enum;

class UpdateProjectTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'milestoneId' => ['nullable', 'integer', 'exists:milestones,id'],
            // REMOVED: parentId validation - subtasks are no longer supported
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', new Enum(ExecutionTaskStatus::class)],
            'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'estimatedHours' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'startDate' => ['nullable', 'date'],
            'dueDate' => ['nullable', 'date'],
            'assigneeId' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                'Validation failed for task update'
            )
        );
    }
}
