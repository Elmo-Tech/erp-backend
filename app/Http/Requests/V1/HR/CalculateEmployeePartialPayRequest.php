<?php

namespace App\Http\Requests\V1\HR;

use App\Models\EmployeePartialPay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculateEmployeePartialPayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_employees') ?? false;
    }

    public function rules(): array
    {
        return [
            'contractId' => ['required', 'integer', 'min:1'],
            'kind' => ['required', 'string', Rule::in([
                EmployeePartialPay::KIND_FIRST_MONTH,
                EmployeePartialPay::KIND_TERMINATION,
            ])],
            'payrollYear' => ['required', 'integer', 'digits:4', 'between:2000,9999'],
            'payrollMonth' => ['required', 'integer', 'between:1,12'],
        ];
    }
}
