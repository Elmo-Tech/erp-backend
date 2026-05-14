<?php

namespace App\Modules\Project\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Project\Actions\UpdateMilestoneProgressAction;
use App\Modules\Project\Models\Milestone;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Requests\StoreMilestoneRequest;
use App\Modules\Project\Requests\UpdateMilestoneRequest;
use App\Modules\Project\Resources\MilestoneResource;
use Illuminate\Http\JsonResponse;

class MilestoneController extends Controller
{
    public function __construct(
        private readonly UpdateMilestoneProgressAction $updateMilestoneProgress,
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * GET /projects/{project}/milestones
     * List all milestones for a project, ordered by creation date.
     */
    public function index(Project $project): JsonResponse
    {
        $milestones = $project->milestones()
            ->withCount(['tasks', 'tasks as done_tasks_count' => fn ($q) => $q->where('status', 'done')])
            ->orderBy('created_at')
            ->get();

        return ApiResponse::success(
            ['milestones' => MilestoneResource::collection($milestones)],
            'Milestones retrieved successfully'
        );
    }

    /**
     * POST /projects/{project}/milestones
     *
     * SECURITY: Only project managers/tech leads or global admins may create milestones.
     */
    public function store(StoreMilestoneRequest $request, Project $project): JsonResponse
    {
        $this->authorize('create', [\App\Modules\Project\Models\ProjectTask::class, $project]);
        $data = $request->validated();

        $milestone = $project->milestones()->create([
            'title' => $data['title'],
            'payment_stage_id' => $data['paymentStageId'] ?? null,
            'invoice_id' => $data['invoiceId'] ?? null,
        ]);

        return ApiResponse::success(
            new MilestoneResource($milestone),
            'Milestone created successfully',
            201
        );
    }

    /**
     * GET /projects/{project}/milestones/{milestone}
     */
    public function show(Project $project, Milestone $milestone): JsonResponse
    {
        $this->assertBelongsToProject($milestone, $project);

        $milestone->load(['tasks.assignee', 'tasks.deliverables']);

        return ApiResponse::success(
            new MilestoneResource($milestone),
            'Milestone retrieved successfully'
        );
    }

    /**
     * PATCH /projects/{project}/milestones/{milestone}
     *
     * SECURITY: Only project managers/tech leads or global admins may update milestones.
     */
    public function update(UpdateMilestoneRequest $request, Project $project, Milestone $milestone): JsonResponse
    {
        $this->assertBelongsToProject($milestone, $project);
        $this->authorize('create', [\App\Modules\Project\Models\ProjectTask::class, $project]);

        $data = $request->validated();

        $milestone->update(array_filter([
            'title' => $data['title'] ?? null,
            'status' => $data['status'] ?? null,
            'payment_stage_id' => array_key_exists('paymentStageId', $data) ? $data['paymentStageId'] : $milestone->payment_stage_id,
            'invoice_id' => array_key_exists('invoiceId', $data) ? $data['invoiceId'] : $milestone->invoice_id,
        ], fn ($v) => $v !== null));

        return ApiResponse::success(
            new MilestoneResource($milestone->refresh()),
            'Milestone updated successfully'
        );
    }

    /**
     * DELETE /projects/{project}/milestones/{milestone}
     *
     * SECURITY: Only project managers/tech leads or global admins may delete milestones.
     */
    public function destroy(Project $project, Milestone $milestone): JsonResponse
    {
        $this->assertBelongsToProject($milestone, $project);
        $this->authorize('delete', [\App\Modules\Project\Models\ProjectTask::class, $milestone->project]);

        $milestone->delete();

        return ApiResponse::success(null, 'Milestone deleted successfully');
    }

    // -------------------------------------------------------------------------

    private function assertBelongsToProject(Milestone $milestone, Project $project): void
    {
        if ($milestone->project_id !== $project->id) {
            abort(404, 'Milestone not found for this project.');
        }
    }
}
