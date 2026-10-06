<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\HR\StoreEmployeeDocumentRequest;
use App\Http\Resources\V1\HR\EmployeeDocumentResource;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Policies\UserAdministrationPolicy;
use App\Services\HR\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeDocumentController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly UserAdministrationPolicy $administrationPolicy,
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        $employee = $this->employee($id);

        if ($employee instanceof JsonResponse) {
            return $employee;
        }

        $this->authorizeEmployeeAccess($request->user(), $employee);

        $documents = $employee->employeeDocuments()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success(EmployeeDocumentResource::collection($documents));
    }

    public function store(StoreEmployeeDocumentRequest $request, int $id): JsonResponse
    {
        $employee = $this->employee($id);

        if ($employee instanceof JsonResponse) {
            return $employee;
        }

        $this->authorizeEmployeeAccess($request->user(), $employee);

        $file = $request->file('file');
        $path = $file->storeAs("employee-documents/{$employee->id}", (string) Str::uuid(), 'local');

        if (! is_string($path)) {
            throw new \RuntimeException('Unable to store employee document.');
        }

        $originalFilename = Str::limit(
            basename(str_replace('\\', '/', $file->getClientOriginalName())),
            255,
            ''
        );

        try {
            $document = DB::transaction(function () use ($employee, $request, $file, $path, $originalFilename): EmployeeDocument {
                return EmployeeDocument::create([
                    'employee_id' => $employee->id,
                    'name' => $request->validated('name'),
                    'document_type' => $request->validated('documentType'),
                    'original_filename' => $originalFilename,
                    'disk' => 'local',
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'uploaded_by' => $request->user()->id,
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        return ApiResponse::success(
            new EmployeeDocumentResource($document),
            'Employee document uploaded successfully',
            201
        );
    }

    public function download(Request $request, EmployeeDocument $document): StreamedResponse|JsonResponse
    {
        $document->loadMissing('employee');
        $this->authorizeEmployeeAccess($request->user(), $document->employee);

        $disk = Storage::disk('local');

        if (! $disk->exists($document->path)) {
            return ApiResponse::notFound('Document file not found');
        }

        return $disk->download(
            $document->path,
            $document->original_filename,
            ['Content-Type' => $document->mime_type ?? 'application/octet-stream']
        );
    }

    public function destroy(Request $request, EmployeeDocument $document): JsonResponse
    {
        $document->loadMissing('employee');
        $this->administrationPolicy->authorizeHrAction($request->user(), $document->employee, 'manage_employee_documents');

        DB::transaction(function () use ($document): void {
            $document = EmployeeDocument::query()
                ->lockForUpdate()
                ->findOrFail($document->id);
            $disk = Storage::disk('local');

            if ($disk->exists($document->path) && ! $disk->delete($document->path)) {
                throw new \RuntimeException('Unable to delete employee document.');
            }

            $document->delete();
        });

        return ApiResponse::success(null, 'Employee document deleted successfully');
    }

    private function employee(int $id): User|JsonResponse
    {
        $employee = $this->employeeService->getEmployee($id);

        return $employee ?? ApiResponse::error(__('messages.employee_not_found'), 404);
    }

    private function authorizeEmployeeAccess(User $actor, User $employee): void
    {
        if (! $actor->is($employee)) {
            $this->administrationPolicy->authorizeEmployeeMutation($actor, $employee);
        }
    }
}
