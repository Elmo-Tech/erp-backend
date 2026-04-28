<?php

namespace App\Modules\HR\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Resources\SalaryAdjustmentCollection;
use App\Modules\HR\Resources\SalaryAdjustmentResource;
use App\Modules\HR\Models\SalaryAdjustment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SalaryAdjustmentController extends Controller
{
    /**
     * Display a listing of salary adjustments
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', SalaryAdjustment::class);

        $adjustments = QueryBuilder::for(SalaryAdjustment::class)
            ->allowedFilters([
                AllowedFilter::exact('user_id'),
                'adjustment_type',
                'status',
                AllowedFilter::exact('payroll_year'),
                AllowedFilter::exact('payroll_month'),
            ])
            ->allowedSorts(['adjustment_date', 'amount', 'created_at'])
            ->allowedIncludes(['user', 'approver', 'reference'])
            ->with(['user'])
            ->paginate($request->get('per_page', 15));

        return response()->json(new SalaryAdjustmentCollection($adjustments));
    }

    /**
     * Display the specified salary adjustment
     */
    public function show(SalaryAdjustment $salaryAdjustment): JsonResponse
    {
        Gate::authorize('view', $salaryAdjustment);

        $salaryAdjustment->load(['user', 'approver', 'reference']);

        return response()->json(new SalaryAdjustmentResource($salaryAdjustment));
    }

    /**
     * Get payroll summary for a specific period
     */
    public function payrollSummary(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', SalaryAdjustment::class);

        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2030',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $adjustments = SalaryAdjustment::forPayrollPeriod($validated['year'], $validated['month'])
            ->with('user')
            ->get();

        // Group by user
        $byUser = $adjustments->groupBy('user_id')->map(function ($userAdjustments, $userId) {
            $user = $userAdjustments->first()->user;
            $totalAdditions = $userAdjustments->filter->isPositive()->sum('amount');
            $totalDeductions = $userAdjustments->filter->isNegative()->sum('amount');

            return [
                'user_id' => $userId,
                'user_name' => $user?->name,
                'base_salary' => $user?->salary ?? 0,
                'total_additions' => $totalAdditions,
                'total_deductions' => abs($totalDeductions),
                'net_adjustment' => $totalAdditions - abs($totalDeductions),
                'adjustments' => SalaryAdjustmentResource::collection($userAdjustments),
            ];
        })->values();

        $summary = [
            'period' => [
                'year' => $validated['year'],
                'month' => $validated['month'],
            ],
            'total_employees' => $byUser->count(),
            'total_additions' => $adjustments->filter->isPositive()->sum('amount'),
            'total_deductions' => abs($adjustments->filter->isNegative()->sum('amount')),
            'net_adjustment' => $adjustments->sum('signed_amount'),
            'by_status' => [
                'pending' => $adjustments->where('status', SalaryAdjustment::STATUS_PENDING)->count(),
                'approved' => $adjustments->where('status', SalaryAdjustment::STATUS_APPROVED)->count(),
                'processed' => $adjustments->where('status', SalaryAdjustment::STATUS_PROCESSED)->count(),
            ],
            'by_type' => [
                'deductions' => $adjustments->where('adjustment_type', SalaryAdjustment::TYPE_DEDUCTION)->sum('amount'),
                'rewards' => $adjustments->where('adjustment_type', SalaryAdjustment::TYPE_REWARD)->sum('amount'),
                'bonuses' => $adjustments->where('adjustment_type', SalaryAdjustment::TYPE_BONUS)->sum('amount'),
                'overtime' => $adjustments->where('adjustment_type', SalaryAdjustment::TYPE_OVERTIME)->sum('amount'),
                'allowances' => $adjustments->where('adjustment_type', SalaryAdjustment::TYPE_ALLOWANCE)->sum('amount'),
                'penalties' => $adjustments->where('adjustment_type', SalaryAdjustment::TYPE_PENALTY)->sum('amount'),
            ],
            'employees' => $byUser,
        ];

        return response()->json($summary);
    }

    /**
     * Approve a salary adjustment
     */
    public function approve(Request $request, SalaryAdjustment $salaryAdjustment): JsonResponse
    {
        Gate::authorize('update', $salaryAdjustment);

        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        if (! $salaryAdjustment->approve(auth()->id(), $validated['notes'] ?? null)) {
            return response()->json([
                'message' => 'Cannot approve this adjustment',
            ], 422);
        }

        return response()->json([
            'message' => 'Salary adjustment approved',
            'data' => new SalaryAdjustmentResource($salaryAdjustment->load(['user', 'approver'])),
        ]);
    }

    /**
     * Mark adjustment as processed (included in payroll)
     */
    public function markProcessed(SalaryAdjustment $salaryAdjustment): JsonResponse
    {
        Gate::authorize('update', $salaryAdjustment);

        if (! $salaryAdjustment->markAsProcessed()) {
            return response()->json([
                'message' => 'Cannot mark this adjustment as processed',
            ], 422);
        }

        return response()->json([
            'message' => 'Salary adjustment marked as processed',
            'data' => new SalaryAdjustmentResource($salaryAdjustment),
        ]);
    }

    /**
     * Cancel a salary adjustment
     */
    public function cancel(Request $request, SalaryAdjustment $salaryAdjustment): JsonResponse
    {
        Gate::authorize('update', $salaryAdjustment);

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        if (! $salaryAdjustment->cancel($validated['reason'])) {
            return response()->json([
                'message' => 'Cannot cancel this adjustment',
            ], 422);
        }

        return response()->json([
            'message' => 'Salary adjustment cancelled',
            'data' => new SalaryAdjustmentResource($salaryAdjustment),
        ]);
    }

    /**
     * Bulk approve adjustments
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', SalaryAdjustment::class);

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|exists:salary_adjustments,id',
        ]);

        $approved = 0;
        $failed = 0;

        DB::beginTransaction();
        try {
            foreach ($validated['ids'] as $id) {
                $adjustment = SalaryAdjustment::find($id);
                if ($adjustment && $adjustment->approve(auth()->id())) {
                    $approved++;
                } else {
                    $failed++;
                }
            }

            DB::commit();

            return response()->json([
                'message' => "Approved {$approved} adjustments",
                'approved' => $approved,
                'failed' => $failed,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to approve adjustments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk mark as processed
     */
    public function bulkProcess(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', SalaryAdjustment::class);

        $validated = $request->validate([
            'year' => 'required|integer',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $count = SalaryAdjustment::forPayrollPeriod($validated['year'], $validated['month'])
            ->where('status', SalaryAdjustment::STATUS_APPROVED)
            ->update(['status' => SalaryAdjustment::STATUS_PROCESSED]);

        return response()->json([
            'message' => "Marked {$count} adjustments as processed",
            'processed_count' => $count,
        ]);
    }
}
