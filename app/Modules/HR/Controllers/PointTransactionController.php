<?php

namespace App\Modules\HR\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Gamification\Models\PointTransaction;
use App\Modules\HR\Resources\PointTransactionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
