<?php

namespace App\Modules\Client\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::in(['company', 'individual'])],
            'name' => 'nullable|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'displayName' => 'nullable|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'legalName' => 'nullable|string|max:255',
            'email' => ['nullable', 'email', 'max:255', Rule::unique('clients', 'primary_email')->ignore($this->client)],
            'primary_email' => ['nullable', 'email', 'max:255', Rule::unique('clients', 'primary_email')->ignore($this->client)],
            'primaryEmail' => ['nullable', 'email', 'max:255', Rule::unique('clients', 'primary_email')->ignore($this->client)],
            'phone' => 'nullable|string|max:50',
            'primary_phone' => 'nullable|string|max:50',
            'primaryPhone' => 'nullable|string|max:50',
            'secondary_phone' => 'nullable|string|max:50',
            'secondaryPhone' => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'companyName' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:30',
            'postalCode' => 'nullable|string|max:30',
            'website' => 'nullable|url|max:255',
            'tax_number' => 'nullable|string|max:100',
            'taxNumber' => 'nullable|string|max:100',
            'source' => 'nullable|string|max:100',
            'lead_source' => 'nullable|string|max:100',
            'leadSource' => 'nullable|string|max:100',
            'account_manager_id' => 'nullable|integer|exists:users,id',
            'accountManagerId' => 'nullable|integer|exists:users,id',
            'interests' => 'nullable|array',
            'interests.*' => 'string|max:100',
            'metadata' => 'nullable|array',
            'contact_person' => 'nullable|array',
            'contactPerson' => 'nullable|array',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError($validator->errors()->toArray())
        );
    }
}
