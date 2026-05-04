<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `project_tasks` table for the Project Execution layer.
 *
 * NOTE: A `tasks` table already exists for the App\Modules\Task module.
 * This is a SEPARATE table scoped to project execution (milestones, sprints,
 * deliverables). The two tables serve different bounded contexts and must
 * NOT be merged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();

            // Nullable: if null the task lives in the project Backlog
            $table->foreignId('milestone_id')
                ->nullable()
                ->constrained('milestones')
                ->nullOnDelete();

            // Self-referencing for subtasks
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('project_tasks')
                ->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // ExecutionTaskStatus enum: backlog | to_do | in_progress | in_review | qa | done
            $table->string('status')->default('to_do');

            $table->decimal('estimated_hours', 8, 2)->nullable();

            // Nullable: unassigned tasks are valid
            $table->foreignId('assignee_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Indexes
            $table->index('project_id');
            $table->index('milestone_id');
            $table->index('parent_id');
            $table->index('status');
            $table->index('assignee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_tasks');
    }
};
