<?php

namespace App\Modules\Project\Requests;

use App\Helpers\ApiResponse;
use App\Modules\Project\Enums\ExecutionTaskStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Enum;

class StoreProjectTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'milestoneId'      => ['nullable', 'integer', 'exists:milestones,id'],
            // REMOVED: parentId validation - subtasks are no longer supported
            'title'            => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'status'           => ['nullable', new Enum(ExecutionTaskStatus::class)],
            'estimatedHours'   => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'startDate'        => ['nullable', 'date'],
            'dueDate'          => ['nullable', 'date'],
            'assigneeId'       => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'        => 'The task title is required.',
            'milestoneId.exists'    => 'The selected milestone does not exist.',
            // REMOVED: parentId validation message - subtasks are no longer supported
            'assigneeId.exists'     => 'The selected assignee does not exist.',
            'estimatedHours.numeric' => 'Estimated hours must be a number.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                'Validation failed for task creation'
            )
        );
    }
}
