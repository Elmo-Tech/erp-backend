<?php

namespace App\Modules\HR\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Requests\ApproveDeductionRequest;
use App\Modules\HR\Requests\StoreEmployeeDeductionRequest;
use App\Modules\HR\Requests\UpdateEmployeeDeductionRequest;
use App\Modules\HR\Resources\EmployeeDeductionCollection;
use App\Modules\HR\Resources\EmployeeDeductionResource;
use App\Modules\HR\Models\DeductionType;
use App\Modules\HR\Models\EmployeeDeduction;
use App\Modules\HR\Models\SalaryAdjustment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EmployeeDeductionController extends Controller
{
    /**
     * Display a listing of employee deductions
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EmployeeDeduction::class);

        $deductions = QueryBuilder::for(EmployeeDeduction::class)
            ->allowedFilters([
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('deduction_type_id'),
                'status',
                'deduction_date',
                AllowedFilter::scope('from_date'),
                AllowedFilter::scope('to_date'),
            ])
            ->allowedSorts(['deduction_date', 'amount', 'created_at'])
            ->allowedIncludes(['user', 'deductionType', 'approver'])
            ->with(['user', 'deductionType'])
            ->paginate($request->get('per_page', 15));

        return response()->json(new EmployeeDeductionCollection($deductions));
    }

    /**
     * Store a newly created employee deduction
     */
    public function store(StoreEmployeeDeductionRequest $request): JsonResponse
    {
        Gate::authorize('create', EmployeeDeduction::class);

        DB::beginTransaction();
        try {
            $validated = $request->validated();

            // Calculate amount if not provided
            if (! isset($validated['amount']) || $validated['amount'] == 0) {
                $deductionType = DeductionType::find($validated['deduction_type_id']);
                $rule = $deductionType->rules()->active()->first();

                if ($rule) {
                    $user = \App\Modules\User\Models\User::find($validated['user_id']);
                    $baseSalary = $user->salary ?? 0;
                    $validated['amount'] = $rule->calculateDeduction(
                        $validated['occurrences'] ?? 1,
                        $baseSalary
                    );
                }
            }

            $deduction = EmployeeDeduction::create($validated);

            // Create salary adjustment if auto-approve is enabled
            if ($deduction->status === EmployeeDeduction::STATUS_APPROVED) {
                SalaryAdjustment::createFromDeduction($deduction);
            }

            DB::commit();

            return response()->json([
                'message' => 'Employee deduction created successfully',
                'data' => new EmployeeDeductionResource($deduction->load(['user', 'deductionType'])),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create employee deduction',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified employee deduction
     */
    public function show(EmployeeDeduction $employeeDeduction): JsonResponse
    {
        Gate::authorize('view', $employeeDeduction);

        $employeeDeduction->load(['user', 'deductionType', 'approver', 'salaryAdjustment']);

        return response()->json(new EmployeeDeductionResource($employeeDeduction));
    }

    /**
     * Update the specified employee deduction
     */
    public function update(UpdateEmployeeDeductionRequest $request, EmployeeDeduction $employeeDeduction): JsonResponse
    {
        Gate::authorize('update', $employeeDeduction);

        if ($employeeDeduction->status !== EmployeeDeduction::STATUS_PENDING) {
            return response()->json([
                'message' => 'Cannot update deduction that is not pending',
            ], 422);
        }

        $employeeDeduction->update($request->validated());

        return response()->json([
            'message' => 'Employee deduction updated successfully',
            'data' => new EmployeeDeductionResource($employeeDeduction->load(['user', 'deductionType'])),
        ]);
    }

    /**
     * Remove the specified employee deduction
     */
    public function destroy(EmployeeDeduction $employeeDeduction): JsonResponse
    {
        Gate::authorize('delete', $employeeDeduction);

        if ($employeeDeduction->status === EmployeeDeduction::STATUS_APPROVED) {
            return response()->json([
                'message' => 'Cannot delete approved deduction',
            ], 422);
        }

        $employeeDeduction->delete();

        return response()->json([
            'message' => 'Employee deduction deleted successfully',
        ]);
    }

    /**
     * Approve the specified employee deduction
     */
    public function approve(ApproveDeductionRequest $request, EmployeeDeduction $employeeDeduction): JsonResponse
    {
        Gate::authorize('approve', $employeeDeduction);

        if (! $employeeDeduction->canBeApproved()) {
            return response()->json([
                'message' => 'This deduction cannot be approved',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $employeeDeduction->approve(auth()->id());

            // Create salary adjustment
            $adjustment = SalaryAdjustment::createFromDeduction($employeeDeduction);

            DB::commit();

            return response()->json([
                'message' => 'Employee deduction approved successfully',
                'data' => new EmployeeDeductionResource($employeeDeduction->load(['user', 'deductionType', 'approver'])),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to approve deduction',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject the specified employee deduction
     */
    public function reject(Request $request, EmployeeDeduction $employeeDeduction): JsonResponse
    {
        Gate::authorize('approve', $employeeDeduction);

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        if (! $employeeDeduction->canBeApproved()) {
            return response()->json([
                'message' => 'This deduction cannot be rejected',
            ], 422);
        }

        $employeeDeduction->reject(auth()->id(), $validated['rejection_reason']);

        return response()->json([
            'message' => 'Employee deduction rejected',
            'data' => new EmployeeDeductionResource($employeeDeduction->load(['user', 'deductionType', 'approver'])),
        ]);
    }

    /**
     * Get deduction summary for an employee
     */
    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EmployeeDeduction::class);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'year' => 'nullable|integer|min:2020|max:2030',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        $query = EmployeeDeduction::where('user_id', $validated['user_id'])
            ->where('status', EmployeeDeduction::STATUS_APPROVED);

        if (isset($validated['year'])) {
            $query->whereYear('deduction_date', $validated['year']);
        }

        if (isset($validated['month'])) {
            $query->whereMonth('deduction_date', $validated['month']);
        }

        $summary = [
            'total_deductions' => $query->count(),
            'total_amount' => $query->sum('amount'),
            'by_type' => $query->select('deduction_type_id', DB::raw('count(*) as count'), DB::raw('sum(amount) as total'))
                ->groupBy('deduction_type_id')
                ->with('deductionType')
                ->get()
                ->map(function ($item) {
                    return [
                        'deduction_type' => $item->deductionType->name,
                        'count' => $item->count,
                        'total' => $item->total,
                    ];
                }),
        ];

        return response()->json($summary);
    }

    /**
     * Bulk create deductions from attendance violations
     */
    public function bulkFromAttendance(Request $request): JsonResponse
    {
        Gate::authorize('create', EmployeeDeduction::class);

        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2030',
            'deduction_type_id' => 'required|exists:deduction_types,id',
            'dry_run' => 'boolean',
        ]);

        $deductionType = DeductionType::with('rules')->findOrFail($validated['deduction_type_id']);

        // Get attendance violations for the period
        $violations = $this->getAttendanceViolations($validated['year'], $validated['month']);

        if ($validated['dry_run'] ?? false) {
            return response()->json([
                'message' => 'Dry run - no deductions created',
                'violations_count' => $violations->count(),
                'preview' => $violations->take(10),
            ]);
        }

        DB::beginTransaction();
        try {
            $created = [];
            foreach ($violations as $violation) {
                $deduction = $this->createDeductionFromViolation($violation, $deductionType);
                if ($deduction) {
                    $created[] = $deduction;
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Bulk deductions created successfully',
                'created_count' => count($created),
                'deductions' => EmployeeDeductionResource::collection(collect($created)),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create bulk deductions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get attendance violations for a period
     */
    protected function getAttendanceViolations(int $year, int $month)
    {
        // This would integrate with the attendance system
        // For now, return empty collection
        return collect([]);
    }

    /**
     * Create deduction from attendance violation
     */
    protected function createDeductionFromViolation($violation, DeductionType $deductionType): ?EmployeeDeduction
    {
        $rule = $deductionType->rules()->active()->first();

        if (! $rule) {
            return null;
        }

        return EmployeeDeduction::create([
            'user_id' => $violation->user_id,
            'deduction_type_id' => $deductionType->id,
            'deduction_date' => $violation->date,
            'amount' => $rule->calculateDeduction(1, $violation->user->salary ?? 0),
            'reason' => $violation->reason ?? 'Attendance violation',
            'status' => EmployeeDeduction::STATUS_PENDING,
            'occurrences' => 1,
        ]);
    }
}
