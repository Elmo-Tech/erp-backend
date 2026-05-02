<?php

namespace App\Modules\Lead\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title'           => ['required', 'string', 'max:255'],
            'company_name'    => ['nullable', 'string', 'max:255'],
            'contact_name'    => ['nullable', 'string', 'max:255'],
            'primary_email'   => ['nullable', 'email', 'max:255'],
            'primary_phone'   => ['nullable', 'string', 'max:50'],
            'expected_budget' => ['nullable', 'numeric', 'min:0'],
            'source'          => ['nullable', 'string', 'max:100'],
            'notes'           => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'        => 'A lead title is required.',
            'primary_email.email'   => 'Please provide a valid email address.',
            'expected_budget.numeric' => 'Budget must be a number.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(
                __('validation.failed'),
                422,
                $validator->errors()->toArray()
            )
        );
    }
}
