<?php

namespace App\Http\Requests\V1\Clients;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreatePortalAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => 'nullable|string|min:8|max:100',
            'sendWelcomeEmail' => 'nullable|boolean',
            'sendEmail' => 'nullable|boolean',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(__('Validation failed'), 422, $validator->errors()->toArray())
        );
    }
}
