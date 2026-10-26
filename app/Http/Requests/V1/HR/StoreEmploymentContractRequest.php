<?php

namespace App\Http\Requests\V1\HR;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreEmploymentContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyPermission(['manage_employee_contracts', 'create_employee_contracts', 'manage_employees']) ?? false;
    }

    public function rules(): array
    {
        return [
            'employeeId' => ['prohibited'],
            'employmentType' => ['required', 'string', Rule::in(['full_time', 'part_time'])],
            'baseSalary' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'requiredMonthlyHours' => ['nullable', 'required_if:employmentType,part_time', 'numeric', 'gt:0', 'max:744', 'decimal:0,2'],
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['prohibited'],
            'terminatedOn' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::requiredIf(fn (): bool => $this->input('status') === 'terminated'),
                'after_or_equal:startDate',
            ],
            'durationMonths' => ['required', 'integer', 'between:1,120'],
            'workDays' => ['required', 'array', 'min:1', 'max:7'],
            'workDays.*' => ['required', 'string', 'distinct', Rule::in([
                'sunday',
                'monday',
                'tuesday',
                'wednesday',
                'thursday',
                'friday',
                'saturday',
            ])],
            'workHoursPerDay' => ['required', 'numeric', 'min:0.25', 'max:24', 'decimal:0,2'],
            'workStartTime' => ['required', 'date_format:H:i'],
            'status' => ['required', 'string', Rule::in(['draft', 'active', 'expired', 'terminated', 'superseded'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(__('validation.failed'), 422, $validator->errors()->toArray())
        );
    }
}
