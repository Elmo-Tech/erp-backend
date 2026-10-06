<?php

namespace App\Http\Requests\V1\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreHrPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => [
                'required',
                'array:workingDays,workStartTime,workEndTime,dailyRateDivisor,unpaidLeaveDailyRateMultiplier,annualLeaveDays,sickLeaveDays,emergencyLeaveDays,leaveWorkflow,monthlyPaidPermissionHours,permissionOverageBehavior,lateDeductionReset,lateDeductionTiers,overtimeRequiresPreapproval,overtimeMultiplier,advanceLimitSalaryPercentage,advanceSettlement,allowAdvanceInstallments,payrollPaymentDay,midMonthHirePaidDays,taskSupervisorLimit',
            ],
            'settings.workingDays' => ['required', 'array', 'min:1', 'max:7'],
            'settings.workingDays.*' => ['required', 'string', 'distinct', 'in:sunday,monday,tuesday,wednesday,thursday,friday,saturday'],
            'settings.workStartTime' => ['required', 'date_format:H:i'],
            'settings.workEndTime' => ['required', 'date_format:H:i'],
            'settings.dailyRateDivisor' => ['required', 'integer', 'between:1,366'],
            'settings.unpaidLeaveDailyRateMultiplier' => ['required', 'numeric', 'min:0'],
            'settings.annualLeaveDays' => ['required', 'integer', 'between:0,366'],
            'settings.sickLeaveDays' => ['required', 'integer', 'between:0,366'],
            'settings.emergencyLeaveDays' => ['required', 'integer', 'between:0,366'],
            'settings.leaveWorkflow' => ['required', 'string', 'in:employee_request_admin_approval,admin_direct,both'],
            'settings.monthlyPaidPermissionHours' => ['required', 'numeric', 'between:0,744'],
            'settings.permissionOverageBehavior' => ['required', 'string', 'max:100'],
            'settings.lateDeductionReset' => ['required', 'string', 'max:100'],
            'settings.lateDeductionTiers' => ['required', 'array', 'min:1', 'max:20'],
            'settings.lateDeductionTiers.*' => ['array:minimumMinutes,maximumMinutes,percentages'],
            'settings.lateDeductionTiers.*.minimumMinutes' => ['required', 'integer', 'between:0,1440'],
            'settings.lateDeductionTiers.*.maximumMinutes' => ['present', 'nullable', 'integer', 'between:0,1440'],
            'settings.lateDeductionTiers.*.percentages' => ['required', 'array', 'size:4'],
            'settings.lateDeductionTiers.*.percentages.*' => ['required', 'integer', 'between:0,100'],
            'settings.overtimeRequiresPreapproval' => ['required', 'boolean'],
            'settings.overtimeMultiplier' => ['required', 'numeric', 'min:0'],
            'settings.advanceLimitSalaryPercentage' => ['required', 'integer', 'between:0,100'],
            'settings.advanceSettlement' => ['required', 'string', 'max:100'],
            'settings.allowAdvanceInstallments' => ['required', 'boolean'],
            'settings.payrollPaymentDay' => ['required', 'integer', 'between:1,31'],
            'settings.midMonthHirePaidDays' => ['required', 'integer', 'between:0,31'],
            'settings.taskSupervisorLimit' => ['required', 'integer', 'in:1'],
            // Client decision 6: exactly one supervisor per task for now.
            // Future multi-supervisor support via a task_supervisors pivot table;
            // keep validation at 1 until that migration ships.
            'effectiveFrom' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateWorkingHours($validator);
            $this->validateLateDeductionTiers($validator);
        });
    }

    private function validateWorkingHours(Validator $validator): void
    {
        $start = $this->input('settings.workStartTime');
        $end = $this->input('settings.workEndTime');

        if (
            is_string($start)
            && is_string($end)
            && preg_match('/^\d{2}:\d{2}$/', $start) === 1
            && preg_match('/^\d{2}:\d{2}$/', $end) === 1
            && $end <= $start
        ) {
            $validator->errors()->add('settings.workEndTime', 'The work end time must be after the work start time.');
        }
    }

    private function validateLateDeductionTiers(Validator $validator): void
    {
        $tiers = $this->input('settings.lateDeductionTiers');

        if (! is_array($tiers)) {
            return;
        }

        $previousMaximum = null;
        $previousTierWasUnbounded = false;

        foreach ($tiers as $index => $tier) {
            if (! is_array($tier)) {
                continue;
            }

            $minimum = $tier['minimumMinutes'] ?? null;
            $maximum = $tier['maximumMinutes'] ?? null;

            if (is_numeric($minimum) && is_numeric($maximum) && (int) $maximum < (int) $minimum) {
                $validator->errors()->add(
                    "settings.lateDeductionTiers.{$index}.maximumMinutes",
                    'The maximum minutes must be greater than or equal to the minimum minutes.'
                );
            }

            if ($previousTierWasUnbounded) {
                $validator->errors()->add(
                    "settings.lateDeductionTiers.{$index}.minimumMinutes",
                    'No tier may follow an unbounded tier.'
                );
            } elseif (is_numeric($minimum) && $previousMaximum !== null && (int) $minimum <= $previousMaximum) {
                $validator->errors()->add(
                    "settings.lateDeductionTiers.{$index}.minimumMinutes",
                    'Late deduction tiers must not overlap.'
                );
            }

            if ($maximum === null) {
                $previousTierWasUnbounded = true;
            } elseif (is_numeric($maximum)) {
                $previousMaximum = (int) $maximum;
            }
        }
    }
}
