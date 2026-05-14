<?php

namespace App\Modules\Finance\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\Subscription;
use App\Modules\Finance\Requests\StoreSubscriptionRequest;
use App\Modules\Finance\Requests\UpdateSubscriptionRequest;
use App\Modules\Finance\Resources\SubscriptionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * GET /finance/subscriptions
     */
    public function index(Request $request): JsonResponse
    {
        abort_if(! $request->user()->can('manage_subscriptions'), 403, 'Unauthorized');

        $subscriptions = Subscription::latest()->paginate(20);

        return ApiResponse::success(
            SubscriptionResource::collection($subscriptions)->response()->getData(true),
            'Subscriptions retrieved successfully'
        );
    }

    /**
     * POST /finance/subscriptions
     */
    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        abort_if(! $request->user()->can('manage_subscriptions'), 403, 'Unauthorized');

        $data = $request->validated();

        $subscription = Subscription::create([
            'client_id' => $data['clientId'] ?? null,
            'project_id' => $data['projectId'] ?? null,
            'name' => $data['name'],
            'provider' => $data['provider'] ?? null,
            'billing_cycle' => $data['billingCycle'],
            'cost' => $data['cost'],
            'currency' => $data['currency'] ?? 'USD',
            'next_billing_date' => $data['nextBillingDate'],
            'status' => $data['status'] ?? 'active',
            'notes' => $data['notes'] ?? null,
        ]);

        return ApiResponse::success(
            new SubscriptionResource($subscription),
            'Subscription created successfully',
            201
        );
    }

    /**
     * GET /finance/subscriptions/{subscription}
     */
    public function show(Request $request, Subscription $subscription): JsonResponse
    {
        abort_if(! $request->user()->can('manage_subscriptions'), 403, 'Unauthorized');

        return ApiResponse::success(
            new SubscriptionResource($subscription),
            'Subscription retrieved successfully'
        );
    }

    /**
     * PUT /finance/subscriptions/{subscription}
     */
    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): JsonResponse
    {
        abort_if(! $request->user()->can('manage_subscriptions'), 403, 'Unauthorized');

        $data = $request->validated();

        $subscription->update(array_filter([
            'client_id' => $data['clientId'] ?? null,
            'project_id' => $data['projectId'] ?? null,
            'name' => $data['name'] ?? null,
            'provider' => array_key_exists('provider', $data) ? $data['provider'] : $subscription->provider,
            'billing_cycle' => $data['billingCycle'] ?? null,
            'cost' => $data['cost'] ?? null,
            'currency' => $data['currency'] ?? null,
            'next_billing_date' => $data['nextBillingDate'] ?? null,
            'status' => $data['status'] ?? null,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $subscription->notes,
        ], fn ($v) => $v !== null));

        return ApiResponse::success(
            new SubscriptionResource($subscription->fresh()),
            'Subscription updated successfully'
        );
    }

    /**
     * DELETE /finance/subscriptions/{subscription}
     */
    public function destroy(Request $request, Subscription $subscription): JsonResponse
    {
        abort_if(! $request->user()->can('manage_subscriptions'), 403, 'Unauthorized');

        $subscription->delete();

        return ApiResponse::success(null, 'Subscription deleted successfully');
    }
}
