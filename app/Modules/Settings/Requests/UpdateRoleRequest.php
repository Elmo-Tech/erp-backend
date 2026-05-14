<?php

namespace App\Modules\Settings\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled in the controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $roleId = $this->route('role'); // Changed from 'id' to 'role'

        return [
            'name' => [
                'sometimes', // Make name optional - only validate if provided
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($roleId),
                'regex:/^[a-z0-9-_]+$/', // Only lowercase letters, numbers, hyphens, and underscores
            ],
            'permissions' => [
                'nullable',
                'array',
            ],
            'permissions.*' => [
                'string',
                'exists:permissions,name',
            ],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.string' => 'The role name must be a string.',
            'name.max' => 'The role name may not be greater than 255 characters.',
            'name.unique' => 'A role with this name already exists.',
            'name.regex' => 'The role name may only contain lowercase letters, numbers, hyphens, and underscores.',
            'permissions.array' => 'The permissions field must be an array.',
            'permissions.*.string' => 'Each permission must be a string.',
            'permissions.*.exists' => 'The selected permission is invalid.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'role name',
            'permissions' => 'permissions',
            'permissions.*' => 'permission',
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
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                'Validation failed'
            )
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert camelCase to snake_case for database compatibility if needed
        // In this case, we keep the role name as provided since it's a user-defined identifier

        // Ensure permissions array is not null
        if (! $this->has('permissions')) {
            $this->merge(['permissions' => []]);
        }
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $roleId = $this->route('role'); // Changed from 'id' to 'role'

            // Validate that the role exists
            try {
                $role = Role::findOrFail($roleId);

                // Additional validation: Check if all provided permissions exist
                if ($this->has('permissions') && is_array($this->permissions)) {
                    $existingPermissions = Permission::pluck('name')->toArray();
                    $invalidPermissions = array_diff($this->permissions, $existingPermissions);

                    if (! empty($invalidPermissions)) {
                        $validator->errors()->add(
                            'permissions',
                            'The following permissions do not exist: '.implode(', ', $invalidPermissions)
                        );
                    }
                }
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                $validator->errors()->add(
                    'role',
                    'The specified role does not exist.'
                );

                return; // Exit early if role doesn't exist
            }
        });
    }
}
