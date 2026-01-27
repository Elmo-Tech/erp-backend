<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration ensures that existing tasks have at least one primary assignee.
     * For tasks that don't have any primary assignees, it will mark the first developer
     * as primary to maintain backward compatibility.
     */
    public function up(): void
    {
        // Find tasks that don't have any primary assignees
        $tasksWithoutPrimary = DB::table('tasks')
            ->leftJoin('task_assignees', function ($join) {
                $join->on('tasks.id', '=', 'task_assignees.task_id')
                     ->where('task_assignees.is_primary', '=', true);
            })
            ->whereNull('task_assignees.id')
            ->pluck('tasks.id');

        foreach ($tasksWithoutPrimary as $taskId) {
            // Get the first developer assignee for this task
            $firstDeveloper = DB::table('task_assignees')
                ->where('task_id', $taskId)
                ->where('role', 'developer')
                ->first();

            if ($firstDeveloper) {
                // Mark the first developer as primary
                DB::table('task_assignees')
                    ->where('id', $firstDeveloper->id)
                    ->update(['is_primary' => true]);
            } else {
                // If no developer exists, mark the first assignee as primary
                $firstAssignee = DB::table('task_assignees')
                    ->where('task_id', $taskId)
                    ->first();

                if ($firstAssignee) {
                    DB::table('task_assignees')
                        ->where('id', $firstAssignee->id)
                        ->update(['is_primary' => true]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration doesn't need to be reversed as it only ensures data consistency
        // Rolling back would potentially break the primary assignee logic
    }
};