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

            // Check if the role exists and if it's protected
            try {
                $role = Role::findOrFail($roleId);

                // Prevent updating protected roles
                if (in_array($role->name, ['founder', 'pmo', 'techdev', 'dev', 'client'])) {
                    $validator->errors()->add(
                        'role',
                        'This role is protected and cannot be modified.'
                    );

                    return; // Exit early if role is protected
                }

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

                // Prevent renaming to protected role names (unless it's already that name)
                // Only check if name is provided
                if ($this->has('name') && $this->name) {
                    $protectedNames = ['founder', 'pmo', 'techdev', 'dev', 'client'];
                    if (in_array($this->name, $protectedNames) && $role->name !== $this->name) {
                        $validator->errors()->add(
                            'name',
                            'Cannot rename role to a protected name. Protected names: '.implode(', ', $protectedNames)
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
