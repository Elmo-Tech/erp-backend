<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Project\ProjectMemberResource;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Notifications\UserAddedToProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectMemberController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
        $this->middleware('permission:manage_project_members|add_project_members')->only('store');
        $this->middleware('permission:manage_project_members')->only('update');
        $this->middleware('permission:manage_project_members|remove_project_members')->only('destroy');
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

        // Check if user is already a member
        $exists = $project->members()->where('user_id', $request->userId)->exists();
        if ($exists) {
            return ApiResponse::error('User is already a member of this project', 400);
        }

        $member = ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $request->userId,
            'role' => $request->role ?? 'member',
        ]);

        $member->load('user:id,name,email,avatar');
        $member->user?->notify(new UserAddedToProject(
            $project->loadMissing('client'),
            $request->user()?->name ?? 'System',
            $request->user()?->id,
            $member->role
        ));

        return ApiResponse::success(
            new ProjectMemberResource($member),
            'Member added successfully',
            201
        );
    }

    public function update(Request $request, Project $project, ProjectMember $member): JsonResponse
    {
        if ($member->project_id !== $project->id) {
            return ApiResponse::error('Member not found', 404);
        }

        $request->validate([
            'role' => 'required|in:lead,member,reviewer',
        ]);

        $member->update(['role' => $request->role]);
        $member->load('user:id,name,email,avatar');

        return ApiResponse::success(
            new ProjectMemberResource($member),
            'Member role updated successfully'
        );
    }

    public function destroy(Project $project, ProjectMember $member): JsonResponse
    {
        if ($member->project_id !== $project->id) {
            return ApiResponse::error('Member not found', 404);
        }

        $member->delete();

        return ApiResponse::success(null, 'Member removed successfully');
    }
}
