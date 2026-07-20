<?php

namespace App\Modules\HR\Controllers;

use App\Enums\PointTransactionType;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Gamification\Models\PointTransaction;
use App\Modules\HR\Models\EmployeeProfile;
use App\Modules\HR\Resources\PointTransactionResource;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Point Transaction Controller — Employee View
 *
 * Exposes the authenticated employee's own gamification point history.
 * No admin-only gate is applied here; every authenticated employee may
 * call this endpoint to inspect their own ledger.
 *
 * Endpoint:
 *   GET /api/v1/hr/my-points
 *
 * Query params:
 *   per_page  (int, default 20, max 100)
 */
class PointTransactionController extends Controller
{
    private const MAX_ABSOLUTE_POINT_BALANCE = 1_000_000;

    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/hr/my-points
    // -------------------------------------------------------------------------

    /**
     * Return a paginated list of the authenticated user's point transactions,
     * ordered newest-first.
     */
    public function myPoints(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 100);

        $transactions = PointTransaction::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return ApiResponse::success(
            PointTransactionResource::collection($transactions)->response()->getData(true),
            'Point transactions retrieved successfully'
        );
    }

    public function adjust(Request $request): JsonResponse
    {
        $data = $request->validate([
            'userId' => ['required', 'integer', 'exists:users,id'],
            'points' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $points = (int) $data['points'];
        $userId = (int) $data['userId'];
        $this->authorizePointAdjustment($request, $userId, $points);

        $transaction = $this->createManualPointTransaction($userId, $points, $data['reason']);

        return ApiResponse::success(
            new PointTransactionResource($transaction),
            'Point adjustment created successfully',
            201
        );
    }

    private function authorizePointAdjustment(Request $request, int $userId, int $points): void
    {
        $user = $request->user();

        if ($points > 0 && $user->id === $userId) {
            abort(403, 'Compensation-bearing point awards require a different target user.');
        }

        if ($user->can('manage_points') || $user->can('manage_user_points')) {
            return;
        }

        if ($points > 0 && $user->can('award_points')) {
            return;
        }

        if ($points < 0 && $user->can('deduct_points')) {
            return;
        }

        abort(403, 'You do not have permission to create this point adjustment.');
    }

    private function createManualPointTransaction(int $userId, int $points, string $reason): PointTransaction
    {
        return DB::transaction(function () use ($userId, $points, $reason): PointTransaction {
            $user = User::query()
                ->whereKey($userId)
                ->lockForUpdate()
                ->with('employeeProfile')
                ->firstOrFail();

            $balanceAfter = (int) ($user->total_points ?? 0) + $points;
            $this->validatePointBounds($user, $points, $balanceAfter);

            $transaction = PointTransaction::create([
                'user_id' => $user->id,
                'points' => $points,
                'type' => $points > 0 ? PointTransactionType::MANUAL_BONUS : PointTransactionType::MANUAL_DEDUCTION,
                'source_type' => null,
                'source_id' => null,
                'reason' => $reason,
                'balance_after' => $balanceAfter,
            ]);

            $user->forceFill(['total_points' => $balanceAfter])->save();

            return $transaction;
        }, 3);
    }

    private function validatePointBounds(User $user, int $points, int $balanceAfter): void
    {
        if ($balanceAfter < 0 || $balanceAfter > self::MAX_ABSOLUTE_POINT_BALANCE) {
            throw ValidationException::withMessages([
                'points' => 'The adjustment would move the point balance outside the allowed range.',
            ]);
        }

        if ($points < 0) {
            return;
        }

        $pointValue = (float) Setting::getValue('gamification_point_value', 50);
        $profile = $user->employeeProfile;
        $baseSalary = $profile instanceof EmployeeProfile ? (float) $profile->base_salary : 0.0;

        if ($pointValue <= 0 || $baseSalary <= 0) {
            throw ValidationException::withMessages([
                'points' => 'A positive point adjustment requires valid payroll point value and employee salary settings.',
            ]);
        }

        $monthlyPointLimit = (int) floor($baseSalary / $pointValue);

        if ($points > $monthlyPointLimit) {
            throw ValidationException::withMessages([
                'points' => "A single award cannot exceed the monthly limit of {$monthlyPointLimit} points.",
            ]);
        }

        $periodStart = Carbon::now()->startOfMonth();
        $monthlyPositivePoints = (int) PointTransaction::query()
            ->where('user_id', $user->id)
            ->where('points', '>', 0)
            ->whereBetween('created_at', [$periodStart, $periodStart->copy()->endOfMonth()])
            ->sum('points');

        if ($monthlyPositivePoints + $points > $monthlyPointLimit) {
            throw ValidationException::withMessages([
                'points' => "The award would exceed the monthly limit of {$monthlyPointLimit} points.",
            ]);
        }
    }
}
