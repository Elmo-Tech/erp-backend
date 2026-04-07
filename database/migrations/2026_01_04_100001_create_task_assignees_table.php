<?php

use App\Traits\CreatedUpdatedByMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use CreatedUpdatedByMigration;

    /**
     * Run the migrations.
     *
     * Multi-assignee support with roles:
     * - developer: Can work on task, create sub-tasks, run timer
     * - reviewer: Can review, add internal notes, approve/reject
     * - lead: Full control over task
     */
    public function up(): void
    {
        Schema::create('task_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['developer', 'reviewer', 'lead'])->default('developer');
            $table->boolean('is_primary')->default(false); // Primary assignee
            $table->integer('points_earned')->default(0); // Points system (+/-)
            $this->addAuditColumns($table);
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
            $table->index(['task_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_assignees');
    }
};
