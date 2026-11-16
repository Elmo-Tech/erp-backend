<?php

namespace App\Http\Controllers;

use App\Domain\Companies\AuthenticatedDatabaseContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AuthorizedCompanyReadController extends Controller
{
    /**
     * Minimal read-only HTTP integration: no GL, payroll or accounting posting API.
     * The server trusts only the previously selected session company, then rechecks it.
     */
    public function show(Request $request, AuthenticatedDatabaseContext $context): JsonResponse
    {
        $companyId = $request->session()->get('erp.company_id');
        if (! is_string($companyId)) {
            return response()->json(['message' => 'Choose an authorized company.'], 403);
        }

        try {
            $company = $context->run($request->user(), $companyId, 'company.view',
                fn (string $authorizedId) => DB::table('companies')
                    ->where('id', $authorizedId)
                    ->first(['id', 'code', 'country_code'])
            );
        } catch (AuthorizationException) {
            $request->session()->forget('erp.company_id');
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json(['company' => $company]);
    }
}
