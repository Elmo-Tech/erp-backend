<?php

namespace App\Services\Client;

use App\Models\Project;

class ProgressService
{
    /**
     * Calculate project progress percentage based on task completion.
     * Returns 0 if no tasks exist.
     * 
     * Requirements: 6.1, 6.2, 6.3, 6.4
     */
    public function calculateProgress(Project $project): int
    {
        // TODO: This will be implemented when Task model is available
        // For now, handle the edge case of no tasks (return 0)
        
        // Check if project has tasks relationship
        if (!method_exists($project, 'tasks')) {
            return 0;
        }
        
        // Get all tasks for the project (including nested/hierarchy)
        $totalTasks = $project->tasks()->count();

        if ($totalTasks === 0) {
            return 0;
        }

        // Count completed tasks
        $completedTasks = $project->tasks()
            ->where('status', 'completed')
            ->count();

        // Calculate percentage: (Completed Tasks / Total Tasks) × 100
        $percentage = ($completedTasks / $totalTasks) * 100;

        // Round to nearest integer
        return (int) round($percentage);
    }
}