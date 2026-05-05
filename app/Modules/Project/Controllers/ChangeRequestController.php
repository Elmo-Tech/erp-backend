<?php

namespace App\Modules\Project\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Project\Models\ChangeRequest;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Requests\StoreChangeRequestRequest;
use App\Modules\Project\Requests\UpdateChangeRequestRequest;
use App\Modules\Project\Resources\ChangeRequestResource;
use Illuminate\Http\JsonResponse;

class ChangeRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * GET /projects/{project}/change-requests
     */
    public function index(Project $project): JsonResponse
    {
        $changeRequests = $project->changeRequests()
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success(
            ['changeRequests' => ChangeRequestResource::collection($changeRequests)],
            'Change requests retrieved successfully'
        );
    }

    /**
     * POST /projects/{project}/change-requests
     * Creates a new change request in DRAFT status.
     */
    public function store(StoreChangeRequestRequest $request, Project $project): JsonResponse
    {
        $data = $request->validated();

        $changeRequest = $project->changeRequests()->create([
            'title' => $data['title'],
            'description' => $data['description'],
            'price' => $data['price'],
            'status' => 'draft',
        ]);

        return ApiResponse::success(
            new ChangeRequestResource($changeRequest),
            'Change request created successfully',
            201
        );
    }

    /**
     * GET /projects/{project}/change-requests/{changeRequest}
     */
    public function show(Project $project, ChangeRequest $changeRequest): JsonResponse
    {
        $this->assertBelongsToProject($changeRequest, $project);

        return ApiResponse::success(
            new ChangeRequestResource($changeRequest),
            'Change request retrieved successfully'
        );
    }

    /**
     * PATCH /projects/{project}/change-requests/{changeRequest}
     */
    public function update(UpdateChangeRequestRequest $request, Project $project, ChangeRequest $changeRequest): JsonResponse
    {
        $this->assertBelongsToProject($changeRequest, $project);

        $changeRequest->update($request->validated());

        return ApiResponse::success(
            new ChangeRequestResource($changeRequest->refresh()),
            'Change request updated successfully'
        );
    }

    /**
     * DELETE /projects/{project}/change-requests/{changeRequest}
     * Only DRAFT change requests can be deleted.
     */
    public function destroy(Project $project, ChangeRequest $changeRequest): JsonResponse
    {
        abort_if(! request()->user()?->can('delete_change_requests'), 403, 'Unauthorized');

        $this->assertBelongsToProject($changeRequest, $project);

        if (! $changeRequest->isDraft()) {
            return ApiResponse::error(
                'Only draft change requests can be deleted.',
                422
            );
        }

        $changeRequest->delete();

        return ApiResponse::success(null, 'Change request deleted successfully');
    }

    // -------------------------------------------------------------------------

    private function assertBelongsToProject(ChangeRequest $changeRequest, Project $project): void
    {
        if ($changeRequest->project_id !== $project->id) {
            abort(404, 'Change request not found for this project.');
        }
    }
}
