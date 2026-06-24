<?php

namespace App\Modules\HR\Controllers;

use App\Enums\PointTransactionType;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Gamification\Models\PointTransaction;
use App\Modules\HR\Resources\PointTransactionResource;
use App\Modules\Settings\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $this->authorizePointAdjustment($request, $points);

        $transaction = $this->createManualPointTransaction((int) $data['userId'], $points, $data['reason']);

        return ApiResponse::success(
            new PointTransactionResource($transaction),
            'Point adjustment created successfully',
            201
        );
    }

    private function authorizePointAdjustment(Request $request, int $points): void
    {
        $user = $request->user();

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
                ->firstOrFail();

            $balanceAfter = (int) ($user->total_points ?? 0) + $points;

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
        });
    }
}
