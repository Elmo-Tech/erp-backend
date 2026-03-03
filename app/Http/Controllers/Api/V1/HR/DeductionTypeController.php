<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\DeductionType;
use App\Models\DeductionRule;
use App\Models\EmployeeDeduction;
use App\Models\RewardType;
use App\Models\EmployeeReward;
use App\Models\SalaryAdjustment;
use App\Http\Resources\V1\HR\DeductionTypeResource;
use App\Http\Resources\V1\HR\DeductionTypeCollection;
use App\Http\Resources\V1\HR\RewardTypeResource;
use App\Http\Resources\V1\HR\RewardTypeCollection;
use App\Http\Resources\V1\HR\EmployeeDeductionResource;
use App\Http\Resources\V1\HR\EmployeeDeductionCollection;
use App\Http\Resources\V1\HR\EmployeeRewardResource;
use App\Http\Resources\V1\HR\EmployeeRewardCollection;
use App\Http\Resources\V1\HR\SalaryAdjustmentResource;
use App\Http\Resources\V1\HR\SalaryAdjustmentCollection;
use App\Http\Requests\V1\HR\StoreDeductionTypeRequest;
use App\Http\Requests\V1\HR\UpdateDeductionTypeRequest;
use App\Http\Requests\V1\HR\StoreRewardTypeRequest;
use App\Http\Requests\V1\HR\UpdateRewardTypeRequest;
use App\Http\Requests\V1\HR\StoreEmployeeDeductionRequest;
use App\Http\Requests\V1\HR\UpdateEmployeeDeductionRequest;
use App\Http\Requests\V1\HR\StoreEmployeeRewardRequest;
use App\Http\Requests\V1\HR\UpdateEmployeeRewardRequest;
use App\Http\Requests\V1\HR\ApproveDeductionRequest;
use App\Http\Requests\V1\HR\ApproveRewardRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Support\Facades\DB;

class DeductionTypeController extends Controller
{
    /**
     * Display a listing of deduction types
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DeductionType::class);

        $deductionTypes = QueryBuilder::for(DeductionType::class)
            ->allowedFilters([
                'name',
                'code',
                'is_active',
                AllowedFilter::exact('category'),
            ])
            ->allowedSorts(['name', 'created_at', 'is_active'])
            ->with(['rules'])
            ->paginate($request->get('per_page', 15));

        return response()->json(new DeductionTypeCollection($deductionTypes));
    }

    /**
     * Store a newly created deduction type
     */
    public function store(StoreDeductionTypeRequest $request): JsonResponse
    {
        $this->authorize('create', DeductionType::class);

        $deductionType = DeductionType::create($request->validated());

        return response()->json([
            'message' => 'Deduction type created successfully',
            'data' => new DeductionTypeResource($deductionType),
        ], 201);
    }

    /**
     * Display the specified deduction type
     */
    public function show(DeductionType $deductionType): JsonResponse
    {
        $this->authorize('view', $deductionType);

        $deductionType->load(['rules', 'employeeDeductions' => function ($query) {
            $query->latest()->limit(10);
        }]);

        return response()->json(new DeductionTypeResource($deductionType));
    }

    /**
     * Update the specified deduction type
     */
    public function update(UpdateDeductionTypeRequest $request, DeductionType $deductionType): JsonResponse
    {
        $this->authorize('update', $deductionType);

        $deductionType->update($request->validated());

        return response()->json([
            'message' => 'Deduction type updated successfully',
            'data' => new DeductionTypeResource($deductionType),
        ]);
    }

    /**
     * Remove the specified deduction type (soft delete)
     */
    public function destroy(DeductionType $deductionType): JsonResponse
    {
        $this->authorize('delete', $deductionType);

        // Check if there are active employee deductions
        $activeDeductions = $deductionType->employeeDeductions()
            ->where('status', '!=', EmployeeDeduction::STATUS_CANCELLED)
            ->count();

        if ($activeDeductions > 0) {
            return response()->json([
                'message' => 'Cannot delete deduction type with active employee deductions',
            ], 422);
        }

        $deductionType->delete();

        return response()->json([
            'message' => 'Deduction type deleted successfully',
        ]);
    }
}