<?php

namespace App\Http\Requests\V1\Tasks;

use App\Enums\TaskStatus;
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
            'parentId' => ['nullable', 'integer', 'exists:tasks,id'],
            'assignedTo' => ['nullable', 'integer', 'exists:users,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'estimatedHours' => ['nullable', 'numeric', 'min:0'],
            'internalReviewerNotes' => ['nullable', 'string'],
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
            'status.enum' => 'The selected status is invalid. Valid values are: todo, in_progress, review, testing, completed.',
            'estimatedHours.numeric' => 'The estimatedHours must be a number.',
            'estimatedHours.min' => 'The estimatedHours must be at least 0.',
            'internalReviewerNotes.string' => 'The internalReviewerNotes must be a string.',
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
}
