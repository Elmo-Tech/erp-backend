<?php

namespace App\Modules\Project\Actions;

use App\Modules\Project\Models\Project;
use Illuminate\Support\Facades\DB;

class UpdateProjectAction
{
    public function __construct(
        private ProjectHistoryAction $historyAction
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Project $project, array $data): Project
    {
        return DB::transaction(function () use ($project, $data) {
            $reason = $data['reason'] ?? null;
            $updateData = [];

            if (isset($data['title'])) {
                $updateData['title'] = $data['title'];
            }

            if (isset($data['description'])) {
                $updateData['description'] = $data['description'];
            }

            if (isset($data['budget']) && (float) $data['budget'] !== (float) $project->budget) {
                $oldBudget = $project->budget;
                $updateData['budget'] = $data['budget'];

                $this->historyAction->recordChange($project, 'budget', $oldBudget, $data['budget'], $reason);
            }

            if (isset($data['deadline'])) {
                $currentDeadline = $project->deadline?->format('Y-m-d');
                $newDeadline = $data['deadline'];

                if ($currentDeadline !== $newDeadline) {
                    $updateData['deadline'] = $newDeadline;

                    $this->historyAction->recordChange($project, 'deadline', $currentDeadline, $newDeadline, $reason);
                }
            }

            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }

            if ($updateData !== []) {
                $project->update($updateData);
            }

            return $project->fresh();
        });
    }
}
