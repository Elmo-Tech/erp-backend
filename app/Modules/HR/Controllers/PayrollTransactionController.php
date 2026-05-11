<?php

namespace App\Modules\HR\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\HR\Models\PayrollTransaction;
use App\Modules\HR\Requests\StorePayrollTransactionRequest;
use App\Modules\HR\Requests\UpdatePayrollTransactionRequest;
use App\Modules\HR\Resources\PayrollTransactionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollTransactionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * GET /hr/payroll-transactions
     * List all manual payroll transactions (paginated). Requires manage_payroll permission.
     */
    public function index(Request $request): JsonResponse
    {
        abort_if(! $request->user()->can('manage_payroll'), 403, 'Unauthorized');

        $transactions = PayrollTransaction::with('user:id,name,email')
            ->when($request->filled('user_id'), fn ($q) => $q->forUser((int) $request->user_id))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->orderByDesc('transaction_date')
            ->paginate($request->integer('perPage', 15));

        return ApiResponse::success(
            PayrollTransactionResource::collection($transactions)->response()->getData(true),
            'Payroll transactions retrieved successfully'
        );
    }

    /**
     * POST /hr/payroll-transactions
     */
    public function store(StorePayrollTransactionRequest $request): JsonResponse
    {
        abort_if(! $request->user()->can('manage_payroll'), 403, 'Unauthorized');

        $transaction = PayrollTransaction::create($request->validated());
        $transaction->load('user:id,name,email');

        return ApiResponse::success(
            new PayrollTransactionResource($transaction),
            'Payroll transaction created successfully',
            201
        );
    }

    /**
     * GET /hr/payroll-transactions/{payrollTransaction}
     */
    public function show(Request $request, PayrollTransaction $payrollTransaction): JsonResponse
    {
        abort_if(! $request->user()->can('manage_payroll'), 403, 'Unauthorized');

        $payrollTransaction->load('user:id,name,email');

        return ApiResponse::success(
            new PayrollTransactionResource($payrollTransaction),
            'Payroll transaction retrieved successfully'
        );
    }

    /**
     * PUT /hr/payroll-transactions/{payrollTransaction}
     */
    public function update(UpdatePayrollTransactionRequest $request, PayrollTransaction $payrollTransaction): JsonResponse
    {
        abort_if(! $request->user()->can('manage_payroll'), 403, 'Unauthorized');

        $payrollTransaction->update($request->validated());

        return ApiResponse::success(
            new PayrollTransactionResource($payrollTransaction->fresh('user')),
            'Payroll transaction updated successfully'
        );
    }

    /**
     * DELETE /hr/payroll-transactions/{payrollTransaction}
     */
    public function destroy(Request $request, PayrollTransaction $payrollTransaction): JsonResponse
    {
        abort_if(! $request->user()->can('manage_payroll'), 403, 'Unauthorized');

        $payrollTransaction->delete();

        return ApiResponse::success(null, 'Payroll transaction deleted successfully');
    }
}
