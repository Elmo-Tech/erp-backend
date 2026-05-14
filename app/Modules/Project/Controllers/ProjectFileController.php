<?php

namespace App\Modules\Project\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Project\Actions\DeleteProjectFileAction;
use App\Modules\Project\Actions\StoreProjectFileAction;
use App\Modules\Project\Actions\UpdateProjectFileAction;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectFile;
use App\Modules\Project\Resources\ProjectFileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ProjectFileController extends Controller
{
    public function __construct(
        private StoreProjectFileAction $storeProjectFileAction,
        private UpdateProjectFileAction $updateProjectFileAction,
        private DeleteProjectFileAction $deleteProjectFileAction
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    public function index(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $files = $project->files()
            ->orderBy('created_at', 'desc')
            ->get();

        return ApiResponse::success([
            'files' => ProjectFileResource::collection($files),
        ], 'Project files retrieved successfully');
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:file,link',
            'file' => 'required_if:type,file|file|max:51200', // 50MB max
            'url' => 'required_if:type,link|nullable|url',
            'description' => 'nullable|string',
            'category' => 'nullable|in:general,design,document,deliverable',
            'isClientVisible' => 'nullable|boolean',
            'isDownloadable' => 'nullable|boolean',
            'visibleToRoles' => 'nullable|array',
        ]);

        $projectFile = $this->storeProjectFileAction->execute($project, $request);

        return ApiResponse::success(
            new ProjectFileResource($projectFile),
            'File added successfully',
            201
        );
    }

    public function show(Project $project, ProjectFile $file): JsonResponse
    {
        $this->authorize('view', $project);

        if ($file->project_id !== $project->id) {
            return ApiResponse::error('File not found', 404);
        }

        return ApiResponse::success(
            new ProjectFileResource($file),
            'File retrieved successfully'
        );
    }

    public function update(Request $request, Project $project, ProjectFile $file): JsonResponse
    {
        $this->authorize('view', $project);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|in:general,design,document,deliverable',
            'isClientVisible' => 'nullable|boolean',
            'isDownloadable' => 'nullable|boolean',
            'visibleToRoles' => 'nullable|array',
        ]);

        try {
            $file = $this->updateProjectFileAction->execute($project, $file, $request);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 404);
        }

        return ApiResponse::success(
            new ProjectFileResource($file),
            'File updated successfully'
        );
    }

    public function destroy(Project $project, ProjectFile $file): JsonResponse
    {
        $this->authorize('view', $project);

        try {
            $this->deleteProjectFileAction->execute($project, $file);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 404);
        }

        return ApiResponse::success(null, 'File deleted successfully');
    }
}
