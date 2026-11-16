<?php

namespace App\Http\Controllers;

use App\Domain\Companies\CompanyAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CompanyController extends Controller
{
    public function index(Request $request, CompanyAuthorization $access): JsonResponse
    {
        return response()->json(['companies' => $access->companyList($request->user())]);
    }

    public function switch(Request $request, CompanyAuthorization $access): JsonResponse
    {
        $data = $request->validate(['company_id' => ['required', 'uuid']]);
        if (! $access->canSelect($request->user(), $data['company_id'])) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        $request->session()->put('erp.company_id', $data['company_id']);
        return $this->context($request, $access);
    }

    public function context(Request $request, CompanyAuthorization $access): JsonResponse
    {
        $companyId = $request->session()->get('erp.company_id');
        if (! is_string($companyId) || ! $access->canSelect($request->user(), $companyId)) {
            $request->session()->forget('erp.company_id');
            return response()->json(['message' => 'Choose an authorized company.'], 403);
        }
        return response()->json([
            'company_id' => $companyId,
            'permissions' => $access->permissions($request->user(), $companyId),
        ]);
    }

    public function authorizeArea(Request $request, string $area, CompanyAuthorization $access): JsonResponse
    {
        if (! in_array($area, ['company.view', 'projects.view', 'finance.view', 'finance.export', 'payroll.view'], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        $companyId = $request->session()->get('erp.company_id');
        if (! is_string($companyId) || ! $access->has($request->user(), $companyId, $area)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        return response()->json(['authorized' => true, 'company_id' => $companyId, 'permission' => $area]);
    }
}
