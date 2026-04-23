<?php

namespace App\Modules\Project\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Project\Actions\AddProjectMemberAction;
use App\Modules\Project\Actions\RemoveProjectMemberAction;
use App\Modules\Project\Actions\UpdateProjectMemberRoleAction;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMember;
use App\Modules\Project\Resources\ProjectMemberResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ProjectMemberController extends Controller
{
    public function __construct(
        private AddProjectMemberAction $addProjectMemberAction,
        private UpdateProjectMemberRoleAction $updateProjectMemberRoleAction,
        private RemoveProjectMemberAction $removeProjectMemberAction
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    public function index(Project $project): JsonResponse
    {
        $members = $project->members()
            ->with('user:id,name,email,avatar')
            ->get();

        return ApiResponse::success([
            'members' => ProjectMemberResource::collection($members),
        ], 'Project members retrieved successfully');
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $request->validate([
            'userId' => 'required|exists:users,id',
            'role' => 'nullable|in:lead,member,reviewer',
        ]);

        try {
            $member = $this->addProjectMemberAction->execute(
                $project,
                (int) $request->userId,
                $request->role ?? 'member'
            );
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 400);
        }

        $member->load('user:id,name,email,avatar');

        return ApiResponse::success(
            new ProjectMemberResource($member),
            'Member added successfully',
            201
        );
    }

    public function update(Request $request, Project $project, ProjectMember $member): JsonResponse
    {
        $request->validate([
            'role' => 'required|in:lead,member,reviewer',
        ]);

        try {
            $member = $this->updateProjectMemberRoleAction->execute($project, $member, $request->role);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 404);
        }

        $member->load('user:id,name,email,avatar');

        return ApiResponse::success(
            new ProjectMemberResource($member),
            'Member role updated successfully'
        );
    }

    public function destroy(Project $project, ProjectMember $member): JsonResponse
    {
        try {
            $this->removeProjectMemberAction->execute($project, $member);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 404);
        }

        return ApiResponse::success(null, 'Member removed successfully');
    }
}
