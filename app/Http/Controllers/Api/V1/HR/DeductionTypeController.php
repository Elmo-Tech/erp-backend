<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\HR\StoreDeductionTypeRequest;
use App\Http\Requests\V1\HR\UpdateDeductionTypeRequest;
use App\Http\Resources\V1\HR\DeductionTypeCollection;
use App\Http\Resources\V1\HR\DeductionTypeResource;
use App\Models\DeductionType;
use App\Models\EmployeeDeduction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

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
            ->when(! config('simplification.enabled'), fn ($query) => $query->with('deductionRules'))
            ->paginate($request->get('per_page', 15));

        return response()->json(new DeductionTypeCollection($deductionTypes));
    }

    /**
     * Store a newly created deduction type
     */
    public function store(StoreDeductionTypeRequest $request): JsonResponse
    {
        $this->authorize('create', DeductionType::class);

        $deductionType = DeductionType::create(array_merge($request->validated(), [
            'calculation_type' => DeductionType::CALCULATION_FIXED,
            'amount' => $request->validated('default_amount') ?? 0,
        ]));

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

        $deductionType->load(['employeeDeductions' => function ($query) {
            $query->latest()->limit(10);
        }]);
        if (! config('simplification.enabled')) {
            $deductionType->load('deductionRules');
        }

        return response()->json(new DeductionTypeResource($deductionType));
    }

    /**
     * Update the specified deduction type
     */
    public function update(UpdateDeductionTypeRequest $request, DeductionType $deductionType): JsonResponse
    {
        $this->authorize('update', $deductionType);

        $attributes = $request->validated();
        if (array_key_exists('default_amount', $attributes)) {
            $attributes['amount'] = $attributes['default_amount'] ?? 0;
        }
        $deductionType->update($attributes);

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
