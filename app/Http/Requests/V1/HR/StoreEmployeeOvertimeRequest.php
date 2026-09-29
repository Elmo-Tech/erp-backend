<?php

namespace App\Http\Requests\V1\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEmployeeOvertimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'overtimeDate' => ['required', 'date_format:Y-m-d'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:2000'],
            'employeeId' => ['prohibited'],
            'userId' => ['prohibited'],
            'user_id' => ['prohibited'],
            'requestedMinutes' => ['prohibited'],
            'requested_minutes' => ['prohibited'],
            'actualMinutes' => ['prohibited'],
            'actual_minutes' => ['prohibited'],
            'approvedMinutes' => ['prohibited'],
            'approved_minutes' => ['prohibited'],
            'status' => ['prohibited'],
            'reviewedBy' => ['prohibited'],
            'reviewed_by' => ['prohibited'],
            'reviewedAt' => ['prohibited'],
            'reviewed_at' => ['prohibited'],
            'reviewNote' => ['prohibited'],
            'review_note' => ['prohibited'],
            'policyVersionId' => ['prohibited'],
            'policy_version_id' => ['prohibited'],
            'hourlyRate' => ['prohibited'],
            'hourly_rate' => ['prohibited'],
            'overtimeMultiplier' => ['prohibited'],
            'overtime_multiplier' => ['prohibited'],
            'payableAmount' => ['prohibited'],
            'payable_amount' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $startTime = $this->input('startTime');
            $endTime = $this->input('endTime');

            if (
                is_string($startTime)
                && is_string($endTime)
                && preg_match('/^\d{2}:\d{2}$/', $startTime) === 1
                && preg_match('/^\d{2}:\d{2}$/', $endTime) === 1
                && $endTime <= $startTime
            ) {
                $validator->errors()->add(
                    'endTime',
                    'The end time must be after the start time and within the same day.'
                );
            }
        });
    }
}
