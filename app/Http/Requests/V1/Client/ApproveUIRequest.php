<?php

namespace App\Http\Requests\V1\Client;

use App\Enums\ProjectStatus;
use App\Helpers\ApiResponse;
use App\Models\Project;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ApproveUIRequest extends FormRequest
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
            // No additional fields required for UI approval
            // Validation is done in withValidator method
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            // Get project from route parameter (Laravel model binding)
            $project = $this->route('project');

            if (!$project) {
                $validator->errors()->add('project', 'Project not found.');
                return;
            }

            // Check if project is in ui_ux status
            if ($project->status !== ProjectStatus::UI_UX) {
                $validator->errors()->add(
                    'status', 
                    'Project must be in UI/UX phase to approve UI deliverables. Current status: ' . $project->status->label()
                );
            }

            // Check if UI is already approved
            if ($project->ui_approved_at) {
                $validator->errors()->add(
                    'approval', 
                    'UI deliverables have already been approved on ' . $project->ui_approved_at->format('Y-m-d H:i:s')
                );
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'project.required' => 'Project is required for UI approval.',
            'status.required' => 'Project must be in UI/UX phase for approval.',
            'approval.required' => 'UI deliverables cannot be approved multiple times.',
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
                'Validation failed for UI approval'
            )
        );
    }
}