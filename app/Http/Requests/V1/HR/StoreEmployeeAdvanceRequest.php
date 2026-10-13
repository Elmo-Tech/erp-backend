<?php

namespace App\Http\Requests\V1\HR;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requestedAmount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999999.99'],
            'reason' => ['required', 'string', 'max:2000'],
            'installmentCount' => ['sometimes', 'integer', 'in:1'],
            'withdrawalDate' => ['nullable', 'date', 'before_or_equal:now'],
            'employeeId' => ['prohibited'],
            'employee_id' => ['prohibited'],
            'approvedAmount' => ['prohibited'],
            'approved_amount' => ['prohibited'],
            'disbursedAmount' => ['prohibited'],
            'disbursed_amount' => ['prohibited'],
            'status' => ['prohibited'],
            'policyVersionId' => ['prohibited'],
            'policy_version_id' => ['prohibited'],
            'policySnapshot' => ['prohibited'],
            'policy_snapshot' => ['prohibited'],
            'salaryAtApproval' => ['prohibited'],
            'salary_at_approval' => ['prohibited'],
            'advanceLimitAmount' => ['prohibited'],
            'advance_limit_amount' => ['prohibited'],
            'approvedBy' => ['prohibited'],
            'approved_by' => ['prohibited'],
            'approvedAt' => ['prohibited'],
            'approved_at' => ['prohibited'],
            'reviewNote' => ['prohibited'],
            'review_note' => ['prohibited'],
            'disbursedBy' => ['prohibited'],
            'disbursed_by' => ['prohibited'],
            'disbursedAt' => ['prohibited'],
            'disbursed_at' => ['prohibited'],
            'disbursementReference' => ['prohibited'],
            'disbursement_reference' => ['prohibited'],
        ];
    }
}
