<?php

namespace App\Http\Requests\V1\Tasks;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\TaskPriority;
use App\Helpers\ApiResponse;
use App\Models\Task;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Requirements: 1.5, 8.1
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'projectId' => ['required', 'integer', 'exists:projects,id'],
            'parentId' => ['nullable', 'integer', 'exists:tasks,id'],
            'assignedTo' => ['nullable', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'acceptanceCriteria' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'type' => ['nullable', Rule::enum(TaskType::class)],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'dueDate' => ['nullable', 'date'],
            'startDate' => ['nullable', 'date'],
            'sprintNumber' => ['nullable', 'integer', 'min:1'],
            'estimatedHours' => ['nullable', 'numeric', 'min:0'],
            'internalReviewerNotes' => ['nullable', 'string'],
            'tagIds' => ['nullable', 'array'],
            'tagIds.*' => ['integer', 'exists:tags,id'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param Validator $validator
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateParentBelongsToSameProject($validator);
            $this->validateTaskTypeHierarchy($validator);
        });
    }

    /**
     * Validate that parent task belongs to the same project.
     */
    private function validateParentBelongsToSameProject(Validator $validator): void
    {
        if (!$this->filled('parentId') || !$this->filled('projectId')) {
            return;
        }

        $parentTask = Task::find($this->input('parentId'));
        
        if ($parentTask && $parentTask->project_id !== (int) $this->input('projectId')) {
            $validator->errors()->add(
                'parentId',
                'The parent task must belong to the same project.'
            );
        }
    }

    /**
     * Validate task type hierarchy:
     * - MAIN tasks cannot have a parent
     * - WEEKLY tasks must have a MAIN parent
     * - SUBTASK tasks must have a WEEKLY parent
     */
    private function validateTaskTypeHierarchy(Validator $validator): void
    {
        $type = $this->input('type');
        $parentId = $this->input('parentId');

        if (!$type) {
            return;
        }

        $taskType = TaskType::tryFrom($type);
        if (!$taskType) {
            return;
        }

        // MAIN tasks cannot have a parent
        if ($taskType === TaskType::MAIN && $parentId) {
            $validator->errors()->add(
                'type',
                'Main tasks cannot have a parent task.'
            );
            return;
        }

        // WEEKLY and SUBTASK must have a parent
        if (in_array($taskType, [TaskType::WEEKLY, TaskType::SUBTASK]) && !$parentId) {
            $validator->errors()->add(
                'parentId',
                $taskType->label() . ' must have a parent task.'
            );
            return;
        }

        // Validate parent type matches expected hierarchy
        if ($parentId) {
            $parentTask = Task::find($parentId);
            if (!$parentTask) {
                return;
            }

            $allowedChildTypes = $parentTask->type->allowedChildTypes();
            
            if (!in_array($taskType, $allowedChildTypes)) {
                $expectedTypes = array_map(fn($t) => $t->label(), $allowedChildTypes);
                $validator->errors()->add(
                    'type',
                    'A ' . $parentTask->type->label() . ' can only have ' . 
                    (empty($expectedTypes) ? 'no children' : implode(' or ', $expectedTypes)) . ' as children.'
                );
            }
        }
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'projectId.required' => 'The projectId field is required.',
            'projectId.integer' => 'The projectId must be an integer.',
            'projectId.exists' => 'The selected projectId does not exist.',
            'parentId.integer' => 'The parentId must be an integer.',
            'parentId.exists' => 'The selected parentId does not exist.',
            'assignedTo.integer' => 'The assignedTo must be an integer.',
            'assignedTo.exists' => 'The selected assignedTo user does not exist.',
            'title.required' => 'The title field is required.',
            'title.string' => 'The title must be a string.',
            'title.max' => 'The title may not be greater than 255 characters.',
            'description.string' => 'The description must be a string.',
            'acceptanceCriteria.string' => 'The acceptanceCriteria must be a string.',
            'status.enum' => 'The selected status is invalid. Valid values are: todo, in_progress, review, testing, completed.',
            'type.enum' => 'The selected type is invalid. Valid values are: main, weekly, subtask.',
            'priority.enum' => 'The selected priority is invalid. Valid values are: low, medium, high, urgent.',
            'dueDate.date' => 'The dueDate must be a valid date.',
            'startDate.date' => 'The startDate must be a valid date.',
            'sprintNumber.integer' => 'The sprintNumber must be an integer.',
            'sprintNumber.min' => 'The sprintNumber must be at least 1.',
            'estimatedHours.numeric' => 'The estimatedHours must be a number.',
            'estimatedHours.min' => 'The estimatedHours must be at least 0.',
            'internalReviewerNotes.string' => 'The internalReviewerNotes must be a string.',
            'tagIds.array' => 'The tagIds must be an array.',
            'tagIds.*.integer' => 'Each tag ID must be an integer.',
            'tagIds.*.exists' => 'One or more selected tags do not exist.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @throws HttpResponseException
     */
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
