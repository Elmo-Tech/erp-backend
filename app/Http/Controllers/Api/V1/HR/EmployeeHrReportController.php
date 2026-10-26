<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Policies\UserAdministrationPolicy;
use App\Services\HR\EmployeeService;
use App\Services\Reporting\EmployeeHrReportService;
use App\Services\Reporting\PdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class EmployeeHrReportController extends Controller
{
    public function __invoke(Request $request, int $id): Response
    {
        $employee = app(EmployeeService::class)->getEmployee($id);
        abort_if($employee === null, 404, 'Employee not found.');
        if (! $request->user()->is($employee)) {
            abort_unless($request->user()->hasAnyPermission(['manage_employees', 'view_employee_reports']), 403);
            app(UserAdministrationPolicy::class)->authorizeHrReadTarget($request->user(), $employee);
        }
        $data = $request->validate([
            'dateFrom' => ['required', 'date_format:Y-m-d'],
            'dateTo' => ['required', 'date_format:Y-m-d', 'after_or_equal:dateFrom'],
            'format' => ['sometimes', 'in:json,pdf'],
        ]);
        $report = app(EmployeeHrReportService::class)->build($employee, Carbon::parse($data['dateFrom']), Carbon::parse($data['dateTo']));

        if (($data['format'] ?? 'json') === 'pdf') {
            return app(PdfService::class)->downloadPdf('reports.employee-hr', ['report' => $report], "employee-{$id}-{$data['dateFrom']}-{$data['dateTo']}.pdf");
        }

        return response()->json(['data' => $report]);
    }
}
