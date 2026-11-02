<?php

namespace App\Http\Requests\V1\Projects;

use App\Helpers\ApiResponse;
use App\Models\Project;
use App\Rules\SelectableProjectStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateProjectStatusRequest extends FormRequest
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
            'status' => [
                'required',
                'string',
                new SelectableProjectStatus(Project::withoutGlobalScopes()->find($this->route('project'))?->status?->value),
            ],
            'reason' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Project status is required',
            'status.string' => 'Project status must be a string',
            'status.enum' => 'Invalid project status provided',
            'reason.string' => 'Reason must be a string',
            'reason.max' => 'Reason cannot exceed 500 characters',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::error(
                'Validation failed for project status update',
                422,
                $validator->errors()->toArray()
            )
        );
    }
}
