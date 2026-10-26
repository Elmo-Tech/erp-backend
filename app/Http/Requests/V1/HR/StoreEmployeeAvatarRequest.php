<?php

namespace App\Http\Requests\V1\HR;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreEmployeeAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyPermission(['manage_employees', 'edit_employees']);
    }

    public function rules(): array
    {
        return [
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(__('validation.failed'), 422, $validator->errors()->toArray())
        );
    }
}
