<?php

namespace App\Http\Requests\V1\Projects;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreDirectProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clientId' => ['required', 'integer', 'exists:clients,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'budget' => ['required', 'numeric', 'min:0'],
            'deadline' => ['nullable', 'date', 'after:today'],
            'status' => ['nullable', 'string', 'in:draft,active,ui_ux,development,testing,completed,on_hold,cancelled'],
            'projectTypeId' => [
                'nullable',
                'integer',
                Rule::exists('lookup_options', 'id')->where(fn ($query) => $query
                    ->where('type', 'project_type')
                    ->where('is_active', true)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'clientId.required' => 'The clientId field is required.',
            'clientId.exists' => 'The selected client does not exist.',
            'title.required' => 'The title field is required.',
            'budget.required' => 'The budget field is required.',
            'budget.min' => 'The budget must be at least 0.',
            'deadline.after' => 'The deadline must be a future date.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                'Validation failed for project creation'
            )
        );
    }
}
