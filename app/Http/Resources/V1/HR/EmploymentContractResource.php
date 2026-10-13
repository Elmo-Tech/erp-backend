<?php

namespace App\Http\Resources\V1\HR;

use App\Models\EmploymentContract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmploymentContractResource extends JsonResource
{
    /** @mixin EmploymentContract */
    public function toArray(Request $request): array
    {
        /** @var EmploymentContract $contract */
        $contract = $this->resource;

        return [
            'id' => $contract->id,
            'employeeId' => $contract->employee_id,
            'employmentType' => $contract->employment_type,
            'baseSalary' => (float) $contract->base_salary,
            'requiredMonthlyHours' => $contract->required_monthly_hours === null ? null : (float) $contract->required_monthly_hours,
            'startDate' => $contract->start_date?->toDateString(),
            'endDate' => $contract->end_date?->toDateString(),
            'terminatedOn' => $contract->terminated_on?->toDateString(),
            'durationMonths' => $contract->duration_months,
            'workDays' => $contract->work_days,
            'workHoursPerDay' => (float) $contract->work_hours_per_day,
            'workStartTime' => $contract->work_start_time,
            'status' => $contract->status,
            'notes' => $contract->notes,
            'isCurrent' => $contract->status === EmploymentContract::STATUS_ACTIVE,
            'hasFile' => $contract->hasFile(),
            'fileName' => $contract->original_filename,
            'fileSize' => $contract->file_size === null ? null : (int) $contract->file_size,
            'fileMimeType' => $contract->mime_type,
            'fileUploadedAt' => $contract->file_uploaded_at?->toISOString(),
            'createdAt' => $contract->created_at?->toISOString(),
            'updatedAt' => $contract->updated_at?->toISOString(),
        ];
    }
}
