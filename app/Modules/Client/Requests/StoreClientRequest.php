<?php

namespace App\Modules\Client\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreClientRequest extends FormRequest
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
            'email' => 'nullable|email|max:255|unique:clients,primary_email',
            'primary_email' => 'nullable|email|max:255|unique:clients,primary_email',
            'primaryEmail' => 'nullable|email|max:255|unique:clients,primary_email',
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
            'contact_person.first_name' => 'nullable|string|max:255',
            'contact_person.last_name' => 'nullable|string|max:255',
            'contact_person.display_name' => 'nullable|string|max:255',
            'contact_person.email' => 'nullable|email|max:255',
            'contact_person.phone' => 'nullable|string|max:50',
            'contact_person.mobile' => 'nullable|string|max:50',
            'contact_person.position' => 'nullable|string|max:255',
            'contact_person.department' => 'nullable|string|max:255',
            'contact_person.is_primary' => 'nullable|boolean',
            'contactPerson' => 'nullable|array',
            'contactPerson.firstName' => 'nullable|string|max:255',
            'contactPerson.lastName' => 'nullable|string|max:255',
            'contactPerson.displayName' => 'nullable|string|max:255',
            'contactPerson.email' => 'nullable|email|max:255',
            'contactPerson.phone' => 'nullable|string|max:50',
            'contactPerson.mobile' => 'nullable|string|max:50',
            'contactPerson.position' => 'nullable|string|max:255',
            'contactPerson.department' => 'nullable|string|max:255',
            'contactPerson.isPrimary' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->hasAnyFilled(['display_name', 'displayName', 'name', 'company_name', 'companyName', 'company'])) {
                $validator->errors()->add('displayName', 'Display name is required.');
            }

            if (! $this->hasAnyFilled(['primary_email', 'primaryEmail', 'email'])) {
                $validator->errors()->add('primaryEmail', 'Email is required.');
            }
        });
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError($validator->errors()->toArray())
        );
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function hasAnyFilled(array $keys): bool
    {
        foreach ($keys as $key) {
            if ($this->filled($key)) {
                return true;
            }
        }

        return false;
    }
}
