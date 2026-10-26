<?php

namespace App\Http\Requests\V1\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDirectEmployeeDeductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyPermission(['manage_employees', 'manage_deductions', 'create_deductions']) ?? false;
    }

    public function rules(): array
    {
        return [
            'deductionTypeId' => [
                'nullable',
                'integer',
                Rule::exists('deduction_types', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')
                ),
            ],
            'deductionDate' => ['required', 'date_format:Y-m-d'],
            'occurredAt' => ['nullable', 'date', 'before_or_equal:now'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99', 'decimal:0,2'],
            'reason' => ['required', 'string', 'max:2000'],
            'employeeId' => ['prohibited'],
            'userId' => ['prohibited'],
            'user_id' => ['prohibited'],
            'attendanceId' => ['prohibited'],
            'attendance_id' => ['prohibited'],
            'lateMinutes' => ['prohibited'],
            'late_minutes' => ['prohibited'],
            'deductibleType' => ['prohibited'],
            'deductible_type' => ['prohibited'],
            'deductibleId' => ['prohibited'],
            'deductible_id' => ['prohibited'],
        ];
    }
}
