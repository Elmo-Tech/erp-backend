<?php

namespace App\Http\Requests\V1\Projects;

use App\Enums\ProjectStatus;
use App\Helpers\ApiResponse;
use App\Models\Project;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'budget' => ['nullable', 'numeric', 'gt:0'],
            'deadline' => ['nullable', 'date', 'after:today'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateReasonRequired($validator);
            $this->validateStatusTransition($validator);
        });
    }

    /**
     * Validate that reason is required when budget or deadline is changed.
     */
    private function validateReasonRequired(Validator $validator): void
    {
        $project = $this->route('project');

        if (! $project instanceof Project) {
            return;
        }

        $budgetChanged = $this->has('budget') && $this->input('budget') !== null;
        $deadlineChanged = $this->has('deadline') && $this->input('deadline') !== null;

        // Check if budget is actually different from current value
        if ($budgetChanged && (float) $this->input('budget') !== (float) $project->budget) {
            if (! $this->filled('reason')) {
                $validator->errors()->add('reason', 'A reason is required when changing the budget.');
            }
        }

        // Check if deadline is actually different from current value
        if ($deadlineChanged) {
            $currentDeadline = $project->deadline?->format('Y-m-d');
            $newDeadline = $this->input('deadline');

            if ($currentDeadline !== $newDeadline) {
                if (! $this->filled('reason')) {
                    $validator->errors()->add('reason', 'A reason is required when changing the deadline.');
                }
            }
        }
    }

    /**
     * Validate that the status transition is allowed.
     */
    private function validateStatusTransition(Validator $validator): void
    {
        if (! $this->filled('status')) {
            return;
        }

        $project = $this->route('project');

        if (! $project instanceof Project) {
            return;
        }

        $currentStatus = $project->status;
        $newStatus = ProjectStatus::tryFrom($this->input('status'));

        if ($newStatus === null) {
            return; // Let the enum validation handle invalid values
        }

        // Same status is not a transition
        if ($currentStatus === $newStatus) {
            return;
        }

        // Special handling for ON_HOLD: can return to previous status
        if ($currentStatus === ProjectStatus::ON_HOLD && $project->previous_status !== null) {
            if ($newStatus === $project->previous_status) {
                return; // Valid transition back to previous status
            }
        }

        if (! $currentStatus->canTransitionTo($newStatus)) {
            $allowedTransitions = array_map(
                fn (ProjectStatus $s) => $s->value,
                $currentStatus->allowedTransitions()
            );

            $allowedList = empty($allowedTransitions)
                ? 'No transitions allowed from this status.'
                : 'Allowed transitions: '.implode(', ', $allowedTransitions);

            $validator->errors()->add(
                'status',
                "Invalid status transition from '{$currentStatus->value}' to '{$newStatus->value}'. {$allowedList}"
            );
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
            'title.string' => 'The title must be a string.',
            'title.max' => 'The title may not be greater than 255 characters.',
            'description.string' => 'The description must be a string.',
            'status.enum' => 'The selected status is invalid. Valid values are: draft, active, ui_ux, development, testing, completed, on_hold, cancelled.',
            'budget.numeric' => 'The budget must be a number.',
            'budget.gt' => 'The budget must be greater than 0.',
            'deadline.date' => 'The deadline must be a valid date.',
            'deadline.after' => 'The deadline must be a future date.',
            'reason.string' => 'The reason must be a string.',
            'reason.max' => 'The reason may not be greater than 1000 characters.',
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
                'Validation failed for project update'
            )
        );
    }
}
