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

class UpdateTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * 
     * Authorization rules:
     * - Admin/Super Admin: Can edit any task type (main, weekly, subtask)
     * - Other users: Can only edit subtasks
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $task = $this->route('task');
        
        if (!$task instanceof Task) {
            return false;
        }
        
        // Admin and Super Admin can edit any task type
        if ($user->hasRole(['admin', 'super-admin'])) {
            return true;
        }
        
        // Other users can only edit subtasks
        if ($task->type === TaskType::SUBTASK) {
            return true;
        }
        
        // Non-admin trying to edit main or weekly task
        return false;
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
            'parentId' => ['nullable', 'integer', 'exists:tasks,id'],
            'assignedTo' => ['nullable', 'integer', 'exists:users,id'],
            'title' => ['nullable', 'string', 'max:255'],
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
            $this->validateStatusTransition($validator);
            $this->validateNoCircularReference($validator);
            $this->validateTaskTypeHierarchy($validator);
        });
    }

    /**
     * Validate that parent task belongs to the same project.
     */
    private function validateParentBelongsToSameProject(Validator $validator): void
    {
        if (!$this->filled('parentId')) {
            return;
        }

        $task = $this->route('task');
        if (!$task instanceof Task) {
            return;
        }

        $parentTask = Task::find($this->input('parentId'));
        
        if ($parentTask && $parentTask->project_id !== $task->project_id) {
            $validator->errors()->add(
                'parentId',
                'The parent task must belong to the same project.'
            );
        }
    }

    /**
     * Validate task type hierarchy when changing type or parent.
     */
    private function validateTaskTypeHierarchy(Validator $validator): void
    {
        $task = $this->route('task');
        if (!$task instanceof Task) {
            return;
        }

        // Get the type (either from request or existing)
        $type = $this->filled('type') ? $this->input('type') : $task->type?->value;
        $parentId = $this->filled('parentId') ? $this->input('parentId') : $task->parent_id;

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
     * Validate that the status transition is allowed.
     */
    private function validateStatusTransition(Validator $validator): void
    {
        if (!$this->filled('status')) {
            return;
        }

        $task = $this->route('task');
        if (!$task instanceof Task) {
            return;
        }

        $currentStatus = $task->status;
        $newStatus = TaskStatus::tryFrom($this->input('status'));

        if ($newStatus === null) {
            return; // Let the enum validation handle invalid values
        }

        // Same status is not a transition
        if ($currentStatus === $newStatus) {
            return;
        }

        if (!$currentStatus->canTransitionTo($newStatus)) {
            $allowedTransitions = array_map(
                fn(TaskStatus $s) => $s->value,
                $currentStatus->allowedTransitions()
            );

            $allowedList = empty($allowedTransitions)
                ? 'No transitions allowed from this status.'
                : 'Allowed transitions: ' . implode(', ', $allowedTransitions);

            $validator->errors()->add(
                'status',
                "Invalid status transition from '{$currentStatus->value}' to '{$newStatus->value}'. {$allowedList}"
            );
        }
    }

    /**
     * Validate that setting a parent doesn't create a circular reference.
     */
    private function validateNoCircularReference(Validator $validator): void
    {
        if (!$this->filled('parentId')) {
            return;
        }

        $task = $this->route('task');
        if (!$task instanceof Task) {
            return;
        }

        $parentId = (int) $this->input('parentId');

        // Cannot set self as parent
        if ($parentId === $task->id) {
            $validator->errors()->add(
                'parentId',
                'A task cannot be its own parent.'
            );
            return;
        }

        // Check if the proposed parent is a descendant of this task
        $descendantIds = $this->getAllDescendantIds($task);
        
        if (in_array($parentId, $descendantIds, true)) {
            $validator->errors()->add(
                'parentId',
                'Cannot set a descendant task as parent (circular reference).'
            );
        }
    }

    /**
     * Get all descendant task IDs recursively.
     *
     * @param Task $task
     * @return array<int>
     */
    private function getAllDescendantIds(Task $task): array
    {
        $ids = [];
        $children = $task->children;

        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getAllDescendantIds($child));
        }

        return $ids;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parentId.integer' => 'The parentId must be an integer.',
            'parentId.exists' => 'The selected parentId does not exist.',
            'assignedTo.integer' => 'The assignedTo must be an integer.',
            'assignedTo.exists' => 'The selected assignedTo user does not exist.',
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
                'Validation failed for task update'
            )
        );
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @throws HttpResponseException
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            ApiResponse::error(
                'Only administrators can edit main tasks and weekly tasks. You can only edit subtasks.',
                403
            )
        );
    }
}
