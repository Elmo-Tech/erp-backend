<?php

namespace App\Modules\Settings\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        if ($actor === null) {
            return false;
        }

        $fields = array_keys($this->all());
        $hasOrdinaryFields = array_intersect($fields, ['name', 'email', 'skills']) !== [];
        $hasPassword = in_array('password', $fields, true);
        $hasRoles = in_array('roles', $fields, true);
        $hasStatus = in_array('isActive', $fields, true);

        if (! $hasOrdinaryFields && ! $hasPassword && ! $hasRoles && ! $hasStatus) {
            return false;
        }

        return (! $hasOrdinaryFields || $actor->hasAnyPermission(['edit_users', 'manage_users']))
            && (! $hasPassword || $actor->can('reset_user_credentials'))
            && (! $hasRoles || $actor->can('assign_user_roles'))
            && (! $hasStatus || $actor->can('toggle_user_status'));
    }

    public function rules(): array
    {
        $userId = $this->route('user');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:100'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'isActive' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => __('validation.email', ['attribute' => 'email']),
            'email.unique' => __('validation.unique', ['attribute' => 'email']),
            'password.min' => __('validation.min.string', ['attribute' => 'password', 'min' => 8]),
            'password.confirmed' => __('validation.confirmed', ['attribute' => 'password']),
            'roles.*.exists' => __('validation.exists', ['attribute' => 'role']),
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError($validator->errors()->toArray(), __('messages.validation_failed'))
        );
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            ApiResponse::forbidden(__('messages.no_permission'))
        );
    }
}
