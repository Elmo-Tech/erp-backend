<?php

namespace App\Modules\HR\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Requests\StoreRewardTypeRequest;
use App\Modules\HR\Requests\UpdateRewardTypeRequest;
use App\Modules\HR\Resources\RewardTypeCollection;
use App\Modules\HR\Resources\RewardTypeResource;
use App\Modules\HR\Models\EmployeeReward;
use App\Modules\HR\Models\RewardType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class RewardTypeController extends Controller
{
    /**
     * Display a listing of reward types
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RewardType::class);

        $rewardTypes = QueryBuilder::for(RewardType::class)
            ->allowedFilters([
                'name',
                'code',
                'is_active',
                AllowedFilter::exact('calculation_type'),
            ])
            ->allowedSorts(['name', 'created_at', 'is_active', 'base_amount'])
            ->withCount('employeeRewards')
            ->paginate($request->get('per_page', 15));

        return response()->json(new RewardTypeCollection($rewardTypes));
    }

    /**
     * Store a newly created reward type
     */
    public function store(StoreRewardTypeRequest $request): JsonResponse
    {
        $this->authorize('create', RewardType::class);

        $rewardType = RewardType::create($request->validated());

        return response()->json([
            'message' => 'Reward type created successfully',
            'data' => new RewardTypeResource($rewardType),
        ], 201);
    }

    /**
     * Display the specified reward type
     */
    public function show(RewardType $rewardType): JsonResponse
    {
        $this->authorize('view', $rewardType);

        $rewardType->load(['employeeRewards' => function ($query) {
            $query->latest()->limit(10)->with('user');
        }]);

        return response()->json(new RewardTypeResource($rewardType));
    }

    /**
     * Update the specified reward type
     */
    public function update(UpdateRewardTypeRequest $request, RewardType $rewardType): JsonResponse
    {
        $this->authorize('update', $rewardType);

        $rewardType->update($request->validated());

        return response()->json([
            'message' => 'Reward type updated successfully',
            'data' => new RewardTypeResource($rewardType),
        ]);
    }

    /**
     * Remove the specified reward type (soft delete)
     */
    public function destroy(RewardType $rewardType): JsonResponse
    {
        $this->authorize('delete', $rewardType);

        // Check if there are active employee rewards
        $activeRewards = $rewardType->employeeRewards()
            ->whereNotIn('status', [EmployeeReward::STATUS_CANCELLED, EmployeeReward::STATUS_REJECTED])
            ->count();

        if ($activeRewards > 0) {
            return response()->json([
                'message' => 'Cannot delete reward type with active employee rewards',
            ], 422);
        }

        $rewardType->delete();

        return response()->json([
            'message' => 'Reward type deleted successfully',
        ]);
    }

    /**
     * Calculate reward amount preview
     */
    public function calculate(Request $request, RewardType $rewardType): JsonResponse
    {
        $this->authorize('view', $rewardType);

        $validated = $request->validate([
            'base_salary' => 'nullable|numeric|min:0',
            'points' => 'nullable|integer|min:0',
            'performance_score' => 'nullable|numeric|min:0|max:5',
        ]);

        $amount = $rewardType->calculateAmount($validated);

        return response()->json([
            'amount' => $amount,
            'calculation_type' => $rewardType->calculation_type,
            'base_amount' => $rewardType->base_amount,
        ]);
    }
}
