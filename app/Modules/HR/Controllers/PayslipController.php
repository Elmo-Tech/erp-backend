<?php

namespace App\Modules\HR\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Payslip;
use App\Modules\HR\Resources\PayslipResource;
use App\Modules\HR\Services\PayrollCalculatorService;
use App\Modules\Settings\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Payslip endpoints.
 *
 * All payroll calculation logic lives in PayrollCalculatorService.
 * This controller is responsible only for HTTP concerns: auth gates,
 * request validation, delegating to the service, and formatting responses.
 *
 * Permission guard: manage_payroll
 */
class PayslipController extends Controller
{
    public function __construct(private readonly PayrollCalculatorService $calculator)
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    // -------------------------------------------------------------------------
    // GET /hr/payslips
    // List payslips — admins see all, employees see only their own.
    // -------------------------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        abort_if(
            ! $request->user()->can('manage_payroll') && ! $request->user()->can('view_payroll'),
            403,
            'Unauthorized'
        );

        $query = Payslip::with('user:id,name,email,avatar');

        // HR admins can filter by employee, month, year, and status.
        if ($request->user()->can('manage_payroll')) {
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->integer('user_id'));
            }

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
        } else {
            // Regular employees see only their own payslips.
            $query->where('user_id', $request->user()->id);
        }

        if ($request->filled('month')) {
            $query->where('month', $request->integer('month'));
        }

        if ($request->filled('year')) {
            $query->where('year', $request->integer('year'));
        }

        $payslips = $query->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate(30);

        return ApiResponse::success(
            PayslipResource::collection($payslips)->response()->getData(true),
            'Payslips retrieved successfully'
        );
    }

    // -------------------------------------------------------------------------
    // GET /hr/payslips/{payslip}
    // Show a single payslip.
    // -------------------------------------------------------------------------

    public function show(Request $request, Payslip $payslip): JsonResponse
    {
        $user = $request->user();

        // HR admins can view any payslip; employees can only view their own.
        abort_if(
            ! $user->can('manage_payroll') && $payslip->user_id !== $user->id,
            403,
            'Unauthorized'
        );

        $payslip->load('user:id,name,email,avatar');

        return ApiResponse::success(
            new PayslipResource($payslip),
            'Payslip retrieved successfully'
        );
    }

    // -------------------------------------------------------------------------
    // POST /hr/payslips/generate
    // Manually trigger payroll calculation for a specific user and month.
    // Intended for HR admins to regenerate or preview a draft payslip.
    // -------------------------------------------------------------------------

    public function generate(Request $request): JsonResponse
    {
        abort_if(! $request->user()->can('manage_payroll'), 403, 'Unauthorized');

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'month'   => ['required', 'integer', 'min:1', 'max:12'],
            'year'    => ['required', 'integer', 'min:2020', 'max:2100'],
        ]);

        /** @var User $employee */
        $employee = User::with('employeeProfile')->findOrFail($validated['user_id']);

        if (! $employee->is_active) {
            return ApiResponse::error('Cannot generate payslip for an inactive employee.', 422);
        }

        try {
            $payslip = $this->calculator->generateForUser(
                $employee,
                (int) $validated['month'],
                (int) $validated['year']
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $payslip->load('user:id,name,email,avatar');

        $message = $payslip->wasRecentlyCreated
            ? 'Payslip generated successfully.'
            : ($payslip->isDraft()
                ? 'Draft payslip recalculated successfully.'
                : 'Payslip already approved or paid — no changes made.');

        return ApiResponse::success(
            new PayslipResource($payslip),
            $message,
            $payslip->wasRecentlyCreated ? 201 : 200
        );
    }

    // -------------------------------------------------------------------------
    // PATCH /hr/payslips/{payslip}/approve
    // Promote a draft payslip to 'approved'.
    // -------------------------------------------------------------------------

    public function approve(Request $request, Payslip $payslip): JsonResponse
    {
        abort_if(! $request->user()->can('manage_payroll'), 403, 'Unauthorized');

        if ($payslip->status !== 'draft') {
            return ApiResponse::error(
                "Payslip is already '{$payslip->status}' and cannot be approved again.",
                422
            );
        }

        $payslip->update(['status' => 'approved']);

        return ApiResponse::success(
            new PayslipResource($payslip->load('user:id,name,email,avatar')),
            'Payslip approved successfully.'
        );
    }

    // -------------------------------------------------------------------------
    // PATCH /hr/payslips/{payslip}/mark-paid
    // Promote an approved payslip to 'paid'.
    // -------------------------------------------------------------------------

    public function markPaid(Request $request, Payslip $payslip): JsonResponse
    {
        abort_if(! $request->user()->can('manage_payroll'), 403, 'Unauthorized');

        if ($payslip->status !== 'approved') {
            return ApiResponse::error(
                "Payslip must be 'approved' before it can be marked as paid. Current status: '{$payslip->status}'.",
                422
            );
        }

        $payslip->update(['status' => 'paid']);

        return ApiResponse::success(
            new PayslipResource($payslip->load('user:id,name,email,avatar')),
            'Payslip marked as paid.'
        );
    }

    // -------------------------------------------------------------------------
    // DELETE /hr/payslips/{payslip}
    // Soft-delete a draft payslip (e.g., generated in error).
    // -------------------------------------------------------------------------

    public function destroy(Request $request, Payslip $payslip): JsonResponse
    {
        abort_if(! $request->user()->can('manage_payroll'), 403, 'Unauthorized');

        if (! $payslip->isDraft()) {
            return ApiResponse::error(
                'Only draft payslips can be deleted.',
                422
            );
        }

        $payslip->delete();

        return ApiResponse::success(null, 'Payslip deleted successfully.');
    }
}
