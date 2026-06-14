<?php

namespace App\Modules\Project\Requests;

use App\Modules\Project\Enums\ProjectStatus;
use App\Helpers\ApiResponse;
use App\Modules\Project\Models\Project;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
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
            'budget' => ['nullable', 'numeric', 'min:0'],
            'deadline' => ['nullable', 'date'],
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
            $this->validateFutureDeadlineWhenChanged($validator);
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
     * Only new deadline changes must be future dates. Existing older deadlines
     * should not block unrelated project edits.
     */
    private function validateFutureDeadlineWhenChanged(Validator $validator): void
    {
        if (! $this->has('deadline') || $this->input('deadline') === null) {
            return;
        }

        $project = $this->route('project');

        if (! $project instanceof Project) {
            return;
        }

        $currentDeadline = $project->deadline?->format('Y-m-d');
        $newDeadline = $this->input('deadline');

        if ($currentDeadline === $newDeadline) {
            return;
        }

        $newDeadlineDate = Carbon::parse($newDeadline)->startOfDay();

        if (! $newDeadlineDate->isAfter(Carbon::today())) {
            $validator->errors()->add('deadline', 'The deadline must be a future date.');
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
            'budget.min' => 'The budget must be at least 0.',
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
