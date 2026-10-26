<?php

namespace App\Http\Requests\V1\HR;

use Illuminate\Foundation\Http\FormRequest;

class SettleEmployeeAdvanceSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyPermission(['manage_employees', 'manage_employee_advances', 'settle_employee_advances']) ?? false;
    }

    public function rules(): array
    {
        return [
            'settlementReference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
