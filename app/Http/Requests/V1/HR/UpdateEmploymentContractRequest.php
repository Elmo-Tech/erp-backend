<?php

namespace App\Http\Requests\V1\HR;

use App\Helpers\ApiResponse;
use App\Models\EmploymentContract;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateEmploymentContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyPermission(['manage_employee_contracts', 'edit_employee_contracts', 'manage_employees']) ?? false;
    }

    public function rules(): array
    {
        return [
            'employeeId' => ['prohibited'],
            'employmentType' => ['sometimes', 'string', Rule::in(['full_time', 'part_time'])],
            'baseSalary' => ['sometimes', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'requiredMonthlyHours' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:744', 'decimal:0,2'],
            'startDate' => ['sometimes', 'date_format:Y-m-d'],
            'endDate' => ['prohibited'],
            'terminatedOn' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'durationMonths' => ['sometimes', 'integer', 'between:1,120'],
            'workDays' => ['sometimes', 'array', 'min:1', 'max:7'],
            'workDays.*' => ['required', 'string', 'distinct', Rule::in([
                'sunday',
                'monday',
                'tuesday',
                'wednesday',
                'thursday',
                'friday',
                'saturday',
            ])],
            'workHoursPerDay' => ['sometimes', 'numeric', 'min:0.25', 'max:24', 'decimal:0,2'],
            'workStartTime' => ['sometimes', 'date_format:H:i'],
            'status' => ['required', 'string', Rule::in(['draft', 'active', 'expired', 'terminated', 'superseded'])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $contract = $this->route('contract');

            if ($contract instanceof EmploymentContract
                && $this->input('employmentType', $contract->employment_type) === 'part_time'
                && ! $this->input('requiredMonthlyHours', $contract->required_monthly_hours)) {
                $validator->errors()->add('requiredMonthlyHours', 'Required monthly hours must be specified for a part-time contract.');
            }

            if (! $contract instanceof EmploymentContract || $this->input('status') !== EmploymentContract::STATUS_TERMINATED) {
                return;
            }

            $terminatedOn = $this->has('terminatedOn')
                ? $this->input('terminatedOn')
                : $contract->terminated_on?->toDateString();
            $startDate = $this->input('startDate', $contract->start_date?->toDateString());

            if ($terminatedOn === null || $terminatedOn === '') {
                $validator->errors()->add('terminatedOn', 'A terminated contract requires a terminatedOn date.');

                return;
            }

            if (
                is_string($terminatedOn)
                && is_string($startDate)
                && preg_match('/^\d{4}-\d{2}-\d{2}$/', $terminatedOn) === 1
                && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) === 1
                && $terminatedOn < $startDate
            ) {
                $validator->errors()->add('terminatedOn', 'The terminatedOn date must be on or after the startDate.');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(__('validation.failed'), 422, $validator->errors()->toArray())
        );
    }
}
