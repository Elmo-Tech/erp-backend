<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\EmployeeReward;
use App\Models\RewardType;
use App\Models\SalaryAdjustment;
use App\Http\Resources\V1\HR\EmployeeRewardResource;
use App\Http\Resources\V1\HR\EmployeeRewardCollection;
use App\Http\Requests\V1\HR\StoreEmployeeRewardRequest;
use App\Http\Requests\V1\HR\UpdateEmployeeRewardRequest;
use App\Http\Requests\V1\HR\ApproveRewardRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EmployeeRewardController extends Controller
{
    /**
     * Display a listing of employee rewards
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EmployeeReward::class);

        $rewards = QueryBuilder::for(EmployeeReward::class)
            ->allowedFilters([
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('reward_type_id'),
                'status',
                'reward_date',
                AllowedFilter::scope('from_date'),
                AllowedFilter::scope('to_date'),
            ])
            ->allowedSorts(['reward_date', 'amount', 'created_at'])
            ->allowedIncludes(['user', 'rewardType', 'approver', 'rewardable'])
            ->with(['user', 'rewardType'])
            ->paginate($request->get('per_page', 15));

        return response()->json(new EmployeeRewardCollection($rewards));
    }

    /**
     * Store a newly created employee reward
     */
    public function store(StoreEmployeeRewardRequest $request): JsonResponse
    {
        Gate::authorize('create', EmployeeReward::class);

        DB::beginTransaction();
        try {
            $validated = $request->validated();

            // Calculate amount if not provided
            if (!isset($validated['amount']) || $validated['amount'] == 0) {
                $rewardType = RewardType::find($validated['reward_type_id']);
                $user = \App\Models\User::find($validated['user_id']);

                $params = [
                    'base_salary' => $user->salary ?? 0,
                    'points' => $validated['points_used'] ?? 0,
                    'performance_score' => $validated['performance_score'] ?? 1,
                ];

                $validated['amount'] = $rewardType->calculateAmount($params);
            }

            $reward = EmployeeReward::create($validated);

            // Create salary adjustment if auto-approve is enabled
            if ($reward->status === EmployeeReward::STATUS_APPROVED) {
                SalaryAdjustment::createFromReward($reward);
            }

            DB::commit();

            return response()->json([
                'message' => 'Employee reward created successfully',
                'data' => new EmployeeRewardResource($reward->load(['user', 'rewardType'])),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create employee reward',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified employee reward
     */
    public function show(EmployeeReward $employeeReward): JsonResponse
    {
        Gate::authorize('view', $employeeReward);

        $employeeReward->load(['user', 'rewardType', 'approver', 'rewardable', 'salaryAdjustment']);

        return response()->json(new EmployeeRewardResource($employeeReward));
    }

    /**
     * Update the specified employee reward
     */
    public function update(UpdateEmployeeRewardRequest $request, EmployeeReward $employeeReward): JsonResponse
    {
        Gate::authorize('update', $employeeReward);

        if ($employeeReward->status !== EmployeeReward::STATUS_PENDING) {
            return response()->json([
                'message' => 'Cannot update reward that is not pending',
            ], 422);
        }

        $employeeReward->update($request->validated());

        return response()->json([
            'message' => 'Employee reward updated successfully',
            'data' => new EmployeeRewardResource($employeeReward->load(['user', 'rewardType'])),
        ]);
    }

    /**
     * Remove the specified employee reward
     */
    public function destroy(EmployeeReward $employeeReward): JsonResponse
    {
        Gate::authorize('delete', $employeeReward);

        if ($employeeReward->status === EmployeeReward::STATUS_APPROVED) {
            return response()->json([
                'message' => 'Cannot delete approved reward',
            ], 422);
        }

        $employeeReward->delete();

        return response()->json([
            'message' => 'Employee reward deleted successfully',
        ]);
    }

    /**
     * Approve the specified employee reward
     */
    public function approve(ApproveRewardRequest $request, EmployeeReward $employeeReward): JsonResponse
    {
        Gate::authorize('approve', $employeeReward);

        if (!$employeeReward->canBeApproved()) {
            return response()->json([
                'message' => 'This reward cannot be approved',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $employeeReward->approve(auth()->id());

            // Create salary adjustment
            $adjustment = SalaryAdjustment::createFromReward($employeeReward);

            DB::commit();

            return response()->json([
                'message' => 'Employee reward approved successfully',
                'data' => new EmployeeRewardResource($employeeReward->load(['user', 'rewardType', 'approver'])),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to approve reward',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject the specified employee reward
     */
    public function reject(Request $request, EmployeeReward $employeeReward): JsonResponse
    {
        Gate::authorize('approve', $employeeReward);

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        if (!$employeeReward->canBeApproved()) {
            return response()->json([
                'message' => 'This reward cannot be rejected',
            ], 422);
        }

        $employeeReward->reject(auth()->id(), $validated['rejection_reason']);

        return response()->json([
            'message' => 'Employee reward rejected',
            'data' => new EmployeeRewardResource($employeeReward->load(['user', 'rewardType', 'approver'])),
        ]);
    }

    /**
     * Mark reward as paid
     */
    public function markAsPaid(Request $request, EmployeeReward $employeeReward): JsonResponse
    {
        Gate::authorize('update', $employeeReward);

        $validated = $request->validate([
            'payment_reference' => 'nullable|string|max:255',
        ]);

        if (!$employeeReward->markAsPaid($validated['payment_reference'] ?? null)) {
            return response()->json([
                'message' => 'Cannot mark this reward as paid',
            ], 422);
        }

        return response()->json([
            'message' => 'Employee reward marked as paid',
            'data' => new EmployeeRewardResource($employeeReward->load(['user', 'rewardType'])),
        ]);
    }

    /**
     * Get reward summary for an employee
     */
    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EmployeeReward::class);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'year' => 'nullable|integer|min:2020|max:2030',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        $query = EmployeeReward::where('user_id', $validated['user_id'])
            ->whereIn('status', [EmployeeReward::STATUS_APPROVED, EmployeeReward::STATUS_PAID]);

        if (isset($validated['year'])) {
            $query->whereYear('reward_date', $validated['year']);
        }

        if (isset($validated['month'])) {
            $query->whereMonth('reward_date', $validated['month']);
        }

        $summary = [
            'total_rewards' => $query->count(),
            'total_amount' => $query->sum('amount'),
            'total_points_used' => $query->sum('points_used'),
            'by_type' => $query->select('reward_type_id', DB::raw('count(*) as count'), DB::raw('sum(amount) as total'), DB::raw('sum(points_used) as points'))
                ->groupBy('reward_type_id')
                ->with('rewardType')
                ->get()
                ->map(function ($item) {
                    return [
                        'reward_type' => $item->rewardType->name,
                        'count' => $item->count,
                        'total' => $item->total,
                        'points_used' => $item->points,
                    ];
                }),
            'by_status' => [
                'pending' => EmployeeReward::where('user_id', $validated['user_id'])
                    ->where('status', EmployeeReward::STATUS_PENDING)
                    ->count(),
                'approved' => EmployeeReward::where('user_id', $validated['user_id'])
                    ->where('status', EmployeeReward::STATUS_APPROVED)
                    ->count(),
                'paid' => EmployeeReward::where('user_id', $validated['user_id'])
                    ->where('status', EmployeeReward::STATUS_PAID)
                    ->count(),
            ],
        ];

        return response()->json($summary);
    }

    /**
     * Create reward from points conversion
     */
    public function fromPoints(Request $request): JsonResponse
    {
        Gate::authorize('create', EmployeeReward::class);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'points' => 'required|integer|min:1',
            'reward_type_id' => 'required|exists:reward_types,id',
            'reason' => 'nullable|string|max:1000',
        ]);

        $user = \App\Models\User::findOrFail($validated['user_id']);
        $rewardType = RewardType::findOrFail($validated['reward_type_id']);

        // Check if user has enough points
        $availablePoints = $user->point_transactions()
            ->where('type', 'credit')
            ->sum('amount') - $user->point_transactions()
            ->where('type', 'debit')
            ->sum('amount');

        if ($availablePoints < $validated['points']) {
            return response()->json([
                'message' => 'Insufficient points balance',
                'available_points' => $availablePoints,
                'requested_points' => $validated['points'],
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Calculate reward amount
            $amount = $rewardType->calculateAmount([
                'points' => $validated['points'],
            ]);

            // Create reward
            $reward = EmployeeReward::create([
                'user_id' => $validated['user_id'],
                'reward_type_id' => $validated['reward_type_id'],
                'reward_date' => now(),
                'amount' => $amount,
                'points_used' => $validated['points'],
                'reason' => $validated['reason'] ?? 'Points conversion',
                'status' => $rewardType->requires_approval 
                    ? EmployeeReward::STATUS_PENDING 
                    : EmployeeReward::STATUS_APPROVED,
            ]);

            // Deduct points from user
            $user->point_transactions()->create([
                'type' => 'debit',
                'amount' => $validated['points'],
                'description' => "Converted to reward: {$rewardType->name}",
                'reference_type' => EmployeeReward::class,
                'reference_id' => $reward->id,
            ]);

            // Create salary adjustment if approved
            if ($reward->status === EmployeeReward::STATUS_APPROVED) {
                SalaryAdjustment::createFromReward($reward);
            }

            DB::commit();

            return response()->json([
                'message' => 'Reward created from points successfully',
                'data' => new EmployeeRewardResource($reward->load(['user', 'rewardType'])),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create reward from points',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk create rewards from performance evaluations
     */
    public function bulkFromEvaluation(Request $request): JsonResponse
    {
        Gate::authorize('create', EmployeeReward::class);

        $validated = $request->validate([
            'evaluation_period_id' => 'required|exists:evaluation_periods,id',
            'reward_type_id' => 'required|exists:reward_types,id',
            'dry_run' => 'boolean',
        ]);

        // Get evaluations for the period
        $evaluations = \App\Models\PerformanceEvaluation::where('evaluation_period_id', $validated['evaluation_period_id'])
            ->where('status', 'completed')
            ->get();

        $rewardType = RewardType::findOrFail($validated['reward_type_id']);

        if ($validated['dry_run'] ?? false) {
            return response()->json([
                'message' => 'Dry run - no rewards created',
                'evaluations_count' => $evaluations->count(),
                'preview' => $evaluations->take(10)->map(function ($eval) use ($rewardType) {
                    return [
                        'user_id' => $eval->user_id,
                        'score' => $eval->overall_score,
                        'estimated_amount' => $rewardType->calculateAmount([
                            'performance_score' => $eval->overall_score,
                            'base_salary' => $eval->user->salary ?? 0,
                        ]),
                    ];
                }),
            ]);
        }

        DB::beginTransaction();
        try {
            $created = [];
            foreach ($evaluations as $evaluation) {
                $amount = $rewardType->calculateAmount([
                    'performance_score' => $evaluation->overall_score,
                    'base_salary' => $evaluation->user->salary ?? 0,
                ]);

                $reward = EmployeeReward::create([
                    'user_id' => $evaluation->user_id,
                    'reward_type_id' => $validated['reward_type_id'],
                    'reward_date' => now(),
                    'amount' => $amount,
                    'reason' => "Performance evaluation reward - Score: {$evaluation->overall_score}",
                    'status' => $rewardType->requires_approval 
                        ? EmployeeReward::STATUS_PENDING 
                        : EmployeeReward::STATUS_APPROVED,
                    'rewardable_type' => \App\Models\PerformanceEvaluation::class,
                    'rewardable_id' => $evaluation->id,
                ]);

                if ($reward->status === EmployeeReward::STATUS_APPROVED) {
                    SalaryAdjustment::createFromReward($reward);
                }

                $created[] = $reward;
            }

            DB::commit();

            return response()->json([
                'message' => 'Bulk rewards created successfully',
                'created_count' => count($created),
                'rewards' => EmployeeRewardResource::collection(collect($created)),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create bulk rewards',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}