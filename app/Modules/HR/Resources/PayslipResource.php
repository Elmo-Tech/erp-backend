<?php

namespace App\Modules\HR\Resources;

use App\Modules\HR\Models\Payslip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payslip
 */
class PayslipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'month' => $this->month,
            'year' => $this->year,
            'period' => sprintf('%04d-%02d', $this->year, $this->month),

            // ── Salary breakdown ──────────────────────────────────────────
            'baseSalary' => (float) $this->base_salary,
            'pointsEarned' => $this->points_earned,
            'pointsBonusAmount' => (float) $this->points_bonus_amount,
            'latenessMinutes' => $this->lateness_minutes,
            'latenessDeductionAmount' => (float) $this->lateness_deduction_amount,
            'netSalary' => (float) $this->net_salary,

            // ── Lifecycle ─────────────────────────────────────────────────
            'status' => $this->status,

            // ── Relations ─────────────────────────────────────────────────
            'employee' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'avatar' => $this->user->avatar,
            ]),

            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
