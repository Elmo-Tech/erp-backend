<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\HR\StoreEmployeeRequest;
use App\Http\Requests\V1\HR\UpdateEmployeeRequest;
use App\Http\Resources\V1\HR\EmployeeCollection;
use App\Http\Resources\V1\HR\EmployeeResource;
use App\Policies\UserAdministrationPolicy;
use App\Services\HR\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Employees",
 *     description="Employee management endpoints"
 * )
 */
class EmployeeController extends Controller
{
    public function __construct(
        private EmployeeService $employeeService,
        private UserAdministrationPolicy $administrationPolicy
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/employees",
     *     summary="List all employees",
     *     tags={"Employees"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="isActive", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="perPage", in="query", @OA\Schema(type="integer", default=15)),
     *
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('filter.search'),
            'isActive' => $request->has('filter.isActive')
                ? filter_var($request->query('filter.isActive'), FILTER_VALIDATE_BOOLEAN)
                : null,
        ];

        $perPage = (int) $request->query('perPage', 15);
        $employees = $this->employeeService->getAllEmployees($filters, $perPage);

        return ApiResponse::success(new EmployeeCollection($employees));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/employees",
     *     summary="Create a new employee",
     *     tags={"Employees"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/StoreEmployeeRequest")),
     *
     *     @OA\Response(response=201, description="Employee created")
     * )
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! empty($data['role'])) {
            $this->administrationPolicy->authorizeEmployeeRoleAssignment(
                $request->user(),
                null,
                $data['role']
            );
        }

        try {
            DB::beginTransaction();

            $employee = $this->employeeService->createEmployee($data);

            DB::commit();

            return ApiResponse::success(
                new EmployeeResource($employee),
                __('messages.employee_created'),
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/employees/{id}",
     *     summary="Get employee details",
     *     tags={"Employees"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $employee = $this->employeeService->getEmployee($id);

        if (! $employee) {
            return ApiResponse::error(__('messages.employee_not_found'), 404);
        }

        return ApiResponse::success(new EmployeeResource($employee));
    }

    /**
     * @OA\Put(
     *     path="/api/v1/employees/{id}",
     *     summary="Update an employee",
     *     tags={"Employees"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/UpdateEmployeeRequest")),
     *
     *     @OA\Response(response=200, description="Employee updated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(UpdateEmployeeRequest $request, int $id): JsonResponse
    {
        $employee = $this->employeeService->getEmployee($id);

        if (! $employee) {
            return ApiResponse::error(__('messages.employee_not_found'), 404);
        }

        $data = $request->validated();
        $this->administrationPolicy->authorizeEmployeeMutation($request->user(), $employee);

        if (! empty($data['role'])) {
            $this->administrationPolicy->authorizeEmployeeRoleAssignment(
                $request->user(),
                $employee,
                $data['role']
            );
        }

        try {
            DB::beginTransaction();

            $employee = $this->employeeService->updateEmployee($employee, $data);

            DB::commit();

            return ApiResponse::success(
                new EmployeeResource($employee),
                __('messages.employee_updated')
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/employees/{id}/performance",
     *     summary="Get employee performance summary",
     *     tags={"Employees"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="year", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="month", in="query", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function performance(Request $request, int $id): JsonResponse
    {
        $employee = $this->employeeService->getEmployee($id);

        if (! $employee) {
            return ApiResponse::error(__('messages.employee_not_found'), 404);
        }

        $year = (int) $request->query('year', date('Y'));
        $month = (int) $request->query('month', date('m'));

        $performance = $this->employeeService->getEmployeePerformance($employee, $year, $month);

        return ApiResponse::success($performance);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/employees/{id}/deactivate",
     *     summary="Deactivate an employee",
     *     tags={"Employees"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Employee deactivated")
     * )
     */
    public function deactivate(int $id): JsonResponse
    {
        $employee = $this->employeeService->getEmployee($id);

        if (! $employee) {
            return ApiResponse::error(__('messages.employee_not_found'), 404);
        }

        $this->administrationPolicy->authorizeEmployeeMutation(request()->user(), $employee);

        $employee = $this->employeeService->deactivateEmployee($employee);

        return ApiResponse::success(
            new EmployeeResource($employee),
            __('messages.employee_deactivated')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/employees/{id}/activate",
     *     summary="Activate an employee",
     *     tags={"Employees"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Employee activated")
     * )
     */
    public function activate(int $id): JsonResponse
    {
        $employee = $this->employeeService->getEmployee($id);

        if (! $employee) {
            return ApiResponse::error(__('messages.employee_not_found'), 404);
        }

        $this->administrationPolicy->authorizeEmployeeMutation(request()->user(), $employee);

        $employee = $this->employeeService->activateEmployee($employee);

        return ApiResponse::success(
            new EmployeeResource($employee),
            __('messages.employee_activated')
        );
    }
}
