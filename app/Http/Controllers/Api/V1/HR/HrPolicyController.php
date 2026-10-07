<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\HR\StoreHrPolicyRequest;
use App\Http\Resources\V1\HR\HrPolicyResource;
use App\Models\HrPolicy;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrPolicyController extends Controller
{
    public function current(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $policy = HrPolicy::query()
            ->effectiveOn($validated['date'] ?? today()->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->firstOrFail();

        return ApiResponse::success(new HrPolicyResource($policy), 'HR policy retrieved successfully');
    }

    public function index(): JsonResponse
    {
        $policies = HrPolicy::query()
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success(HrPolicyResource::collection($policies), 'HR policies retrieved successfully');
    }

    public function store(StoreHrPolicyRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $policy = HrPolicy::create([
            'settings' => $validated['settings'],
            'effective_from' => $validated['effectiveFrom'] ?? today()->toDateString(),
            'created_by' => $request->user()->id,
            'notes' => $validated['notes'] ?? null,
        ]);
        app(AuditService::class)->log('hr', 'policy_created', $policy, ['effectiveFrom' => $policy->effective_from?->toDateString(), 'notes' => $policy->notes], 'warning', 'HR policy version created');

        return ApiResponse::success(new HrPolicyResource($policy), 'HR policy created successfully', 201);
    }
}
