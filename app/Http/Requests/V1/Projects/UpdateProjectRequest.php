<?php

namespace App\Http\Requests\V1\Projects;

use App\Helpers\ApiResponse;
use App\Models\LookupOption;
use App\Models\Project;
use App\Rules\SelectableProjectStatus;
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
        $project = $this->route('project');
        $unchangedDeadline = $project instanceof Project
            && $project->deadline?->format('Y-m-d') === $this->input('deadline');

        return [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', new SelectableProjectStatus($this->route('project') instanceof Project ? $this->route('project')->status?->value : null)],
            'budget' => ['nullable', 'numeric', 'gt:0'],
            'deadline' => array_filter(['nullable', 'date', $unchangedDeadline ? null : 'after_or_equal:today']),
            'projectTypeId' => [
                'nullable',
                'integer',
                Rule::exists('lookup_options', 'id')->where(fn ($query) => $query
                    ->where('type', 'project_type')),
            ],
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
            $this->validateProjectTypeIsActive($validator);
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
     * Inactive project types can only remain assigned to their existing project.
     */
    private function validateProjectTypeIsActive(Validator $validator): void
    {
        if (! $this->filled('projectTypeId')) {
            return;
        }

        $project = $this->route('project');
        $projectTypeId = (int) $this->input('projectTypeId');

        if (! $project instanceof Project || (int) $project->project_type_id === $projectTypeId) {
            return;
        }

        $isInactiveProjectType = LookupOption::ofType('project_type')
            ->whereKey($projectTypeId)
            ->where('is_active', false)
            ->exists();

        if ($isInactiveProjectType) {
            $validator->errors()->add('projectTypeId', 'The selected project type is inactive.');
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
