<?php

namespace App\Modules\Task\Requests\Tasks;

use App\Enums\TaskCategory;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Helpers\ApiResponse;
use App\Modules\Task\Models\Task;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization rules based on permissions:
     * - create_main_tasks: Can create main tasks
     * - create_weekly_tasks: Can create weekly tasks
     * - create_subtasks: Can create subtasks
     * - Admin/Super Admin: Can create any task type (bypass permission check)
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $taskType = $this->input('type', TaskType::MAIN->value);

        // Admin and Super Admin can create any task type
        if ($user->hasRole(['admin', 'super-admin'])) {
            return true;
        }

        // Check specific permission based on task type
        return match ($taskType) {
            TaskType::MAIN->value => $user->can('create_main_tasks'),
            TaskType::WEEKLY->value => $user->can('create_weekly_tasks'),
            TaskType::SUBTASK->value => $user->can('create_subtasks'),
            default => false,
        };
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
            'projectId' => ['nullable', 'integer', 'exists:projects,id'],
            'parentId' => ['nullable', 'integer', 'exists:tasks,id'],
            'assignedTo' => ['nullable', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'acceptanceCriteria' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'type' => ['nullable', Rule::enum(TaskType::class)],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'taskCategory' => ['nullable', Rule::enum(TaskCategory::class)],
            'dueDate' => ['nullable', 'date'],
            'expectedResolutionDate' => ['nullable', 'date'],
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
        if (! $this->filled('parentId')) {
            return;
        }

        $parentTask = Task::find($this->input('parentId'));

        if (! $parentTask) {
            return;
        }

        // If creating a task with a parent, inherit project from parent if not specified
        if (! $this->filled('projectId') && $parentTask->project_id) {
            $this->merge(['projectId' => $parentTask->project_id]);

            return;
        }

        // If both projectId and parentId are specified, they must match
        if ($this->filled('projectId') && $parentTask->project_id !== (int) $this->input('projectId')) {
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

        if (! $type) {
            return;
        }

        $taskType = TaskType::tryFrom($type);
        if (! $taskType) {
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
        if (in_array($taskType, [TaskType::WEEKLY, TaskType::SUBTASK]) && ! $parentId) {
            $validator->errors()->add(
                'parentId',
                $taskType->label().' must have a parent task.'
            );

            return;
        }

        // Validate parent type matches expected hierarchy
        if ($parentId) {
            $parentTask = Task::find($parentId);
            if (! $parentTask) {
                return;
            }

            $allowedChildTypes = $parentTask->type->allowedChildTypes();

            if (! in_array($taskType, $allowedChildTypes)) {
                $expectedTypes = array_map(fn ($t) => $t->label(), $allowedChildTypes);
                $validator->errors()->add(
                    'type',
                    'A '.$parentTask->type->label().' can only have '.
                    (empty($expectedTypes) ? 'no children' : implode(' or ', $expectedTypes)).' as children.'
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
            'taskCategory.enum' => 'The selected taskCategory is invalid. Valid values are: backend, frontend, api, database, ui, ux, ui_ux, component, styling, design, prototype, wireframe, mobile, ios, android, react_native, deployment, infrastructure, ci_cd, monitoring, testing, automation, bug_fix, quality_assurance, server, general.',
            'dueDate.date' => 'The dueDate must be a valid date.',
            'expectedResolutionDate.date' => 'The expectedResolutionDate must be a valid date.',
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

    /**
     * Handle a failed authorization attempt.
     *
     * @throws HttpResponseException
     */
    protected function failedAuthorization(): void
    {
        $taskType = $this->input('type', TaskType::MAIN->value);

        $message = match ($taskType) {
            TaskType::MAIN->value => 'You do not have permission to create main tasks.',
            TaskType::WEEKLY->value => 'You do not have permission to create weekly tasks.',
            TaskType::SUBTASK->value => 'You do not have permission to create subtasks.',
            default => 'You do not have permission to create this task type.',
        };

        throw new HttpResponseException(
            ApiResponse::error($message, 403)
        );
    }
}
